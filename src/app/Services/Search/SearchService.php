<?php

namespace Backpack\Store\app\Services\Search;

use Backpack\Store\app\Models\Catalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Backpack\Store\app\Job\LogSearchQueryJob;
use Laravel\Scout\Builder;

class SearchService
{

    public function searchProducts(string $q, string $countryCode, int $perPage = 20, ?bool $onlyInStock = null): array
    {
        $onlyInStock = $onlyInStock ?? (bool) \Settings::get(
            'dress.search.only_in_stock',
            config('dress.search.only_in_stock', true)
        );

        $driver = \Settings::get('dress.search.driver', 'meilisearch');
        if (!\Settings::get('dress.search.enabled', false) || $driver !== 'meilisearch') {
            return $this->dbFallback($q, $countryCode, $perPage, $onlyInStock);
        }

        $started = microtime(true);
        $results = null;

        $locale = $this->countryToLocale($countryCode);
        $norm = app(QueryNormalizer::class)->variants($q, $locale);

        $builder = Catalog::search($norm[0]);
        $this->applyDefaultOptions($builder, $locale, $onlyInStock);
        
        // ранжирование/сортировка — по настройкам
        if ($sort = \Settings::get('dress.search.ranking.sort', [])) {
            // пример: ["price:asc","popularity:desc"]
            foreach ($sort as $rule) {
                [$field, $dir] = array_pad(explode(':', $rule, 2), 2, 'asc');
                $builder->orderBy($field, $dir);
            }
        }

        // если первый вариант не дал результатов — пробуем варианты
        /** @var LengthAwarePaginator $page */
        $page = $builder->paginate($perPage);

        if ($page->total() === 0 && count($norm) > 1) {
            foreach (array_slice($norm, 1) as $alt) {
                $altBuilder = Catalog::search($alt);
                $this->applyDefaultOptions($altBuilder, $locale, $onlyInStock);
                
                $page = $altBuilder->paginate($perPage);
                if ($page->total() > 0) {
                    LogSearchQueryJob::dispatchNowOrQueue(
                        q: $q,
                        normalized: $norm,
                        countryCode: $countryCode,
                        locale: $locale,
                        userId: optional(auth()->user())->id,
                        ip: request()->ip(),
                        resultsCount: $page->total() ?? 0,
                        tookMs: (int) round((microtime(true) - $started) * 1000),
                        driver: 'meilisearch'
                    );

                    return ['meta' => $this->meta($page), 'data' => $page, 'suggestion' => $alt];
                }
            }
        }

        LogSearchQueryJob::dispatchNowOrQueue(
            q: $q,
            normalized: $norm,
            countryCode: $countryCode,
            locale: $locale,
            userId: optional(auth()->user())->id,
            ip: request()->ip(),
            resultsCount: $page->total() ?? 0,
            tookMs: (int) round((microtime(true) - $started) * 1000),
            driver: 'meilisearch'
        );

        return ['meta' => $this->meta($page), 'data' => $page];
    }


    protected function dbFallback(string $q, string $country, int $perPage, bool $onlyInStock): array
    {
        $locale = $this->countryToLocale($country);
        $query = Catalog::query()->where('country_code', $country)->where('is_available', 1);

        if ($onlyInStock) {
            $query->where('in_stock', '>', 0);
        }

        // мульти-язычный LIKE по name_*/attrs_text_*
        $locales = \Settings::get('dress.search.multilang.enabled', true)
            ? (array)\Settings::get('dress.locales', [$locale])
            : [$locale];

        $query->where(function ($w) use ($q, $locales) {
            foreach ($locales as $loc) {
                $w->orWhere("name_{$loc}", 'like', "%{$q}%")
                  ->orWhere("attrs_text_{$loc}", 'like', "%{$q}%");
            }
        });

        $page = $query->paginate($perPage);
        return ['meta' => $this->meta($page), 'data' => $page->items()];
    }

    protected function applyDefaultOptions(Builder $builder, string $locale, bool $onlyInStock): void
    {
        $options = $builder->options ?? [];

        if (\Settings::get('dress.search.multilang.default_per_country', false)) {
            $options['attributesToSearchOn'] = [
                "name_{$locale}",
                "brand_{$locale}",
                "categories_{$locale}",
                "attrs_text_{$locale}",
            ];
        }

        if ($onlyInStock) {
            $options['filter'] = $this->mergeFilters($options['filter'] ?? null, 'in_stock > 0');
        }

        if (!empty($options)) {
            $builder->options($options);
        }
    }

    protected function mergeFilters($current, string $clause)
    {
        if (empty($current)) {
            return $clause;
        }

        if (is_array($current)) {
            $current[] = $clause;
            return $current;
        }

        return "{$current} AND {$clause}";
    }

    protected function countryToLocale(string $country): string
    {
        // твоя мапа страна -> дефолтная локаль
        // $map = \Settings::get('dress.country.default_locale', ['UA'=>'uk','CZ'=>'cs']);
        $map = \Store::defaultCountryLocales();

        return $map[$country] ?? app()->getLocale();
    }

    protected function meta(LengthAwarePaginator $p): array
    {
        return [
            'driver'   => 'meilisearch',
            'page'     => $p->currentPage(),
            'per_page' => $p->perPage(),
            'total'    => $p->total(),
            'has_more' => $p->hasMorePages(),
        ];
    }
}
