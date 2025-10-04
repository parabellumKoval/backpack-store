<?php

namespace Backpack\Store\app\Services\Search;

use Backpack\Store\app\Models\Catalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Backpack\Store\app\Job\LogSearchQueryJob;

class SearchService
{
    public function searchProducts(string $q, string $countryCode, int $perPage = 20): array
    {
        $driver = \Settings::get('dress.search.driver', 'meilisearch');
        if (!\Settings::get('dress.search.enabled', false) || $driver !== 'meilisearch') {
            return $this->dbFallback($q, $countryCode, $perPage);
        }

        $started = microtime(true);
        $results = null;

        $locale = $this->countryToLocale($countryCode);
        $norm = app(QueryNormalizer::class)->variants($q, $locale);

        $builder = Catalog::search($norm[0]);

        // фильтр по стране (если используешь суффикс индекса — можно не фильтровать)
        $builder->where('country_code', $countryCode);

        // мультиязычие на стороне индекса:
        // searchableAttributes уже включают name_*, categories_*, attrs_text_*
        // если нужно "только дефолт языка страны":
        if (\Settings::get('dress.search.multilang.default_per_country', false)) {
            $builder->options([
                'attributesToSearchOn' => ["name_{$locale}","brand_{$locale}","categories_{$locale}","attrs_text_{$locale}"]
            ]);
        }

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
                $altBuilder = Catalog::search($alt)->where('country_code', $countryCode);
                
                if (\Settings::get('dress.search.multilang.default_per_country', false)) {
                    $altBuilder->options(['attributesToSearchOn' => ["name_{$locale}","brand_{$locale}","categories_{$locale}","attrs_text_{$locale}"]]);
                }
                
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

                    return ['meta' => $this->meta($page), 'data' => $page->items(), 'suggestion' => $alt];
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

        return ['meta' => $this->meta($page), 'data' => $page->items()];
    }


    protected function dbFallback(string $q, string $country, int $perPage): array
    {
        $locale = $this->countryToLocale($country);
        $query = Catalog::query()->where('country_code', $country)->where('is_available', 1);

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

    protected function countryToLocale(string $country): string
    {
        // твоя мапа страна -> дефолтная локаль
        $map = \Settings::get('dress.country.default_locale', ['UA'=>'uk','CZ'=>'cs']);
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
