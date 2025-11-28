<?php

namespace Backpack\Store\app\Library\ServiceOperation\Similar\Providers;

use Backpack\CRUD\app\Library\ServiceOperation\Similar\Contracts\SimilarSearchProvider;
use Backpack\CRUD\app\Library\ServiceOperation\Similar\Providers\DatabaseSimilarSearchProvider;
use Backpack\CRUD\app\Library\ServiceOperation\Similar\SimilarSearchContext;
use Backpack\Store\app\Services\Search\QueryNormalizer;
use Illuminate\Support\Collection;
use Meilisearch\Client;

class ProductMeilisearchProvider implements SimilarSearchProvider
{
    public function search(SimilarSearchContext $context, array $params = []): Collection
    {
        $fields = $context->getFields();
        if ($fields === []) {
            return collect();
        }

        $client = $this->makeClient();
        if (! $client) {
            return $this->fallback($context, $params);
        }

        $definition = $context->getDefinition();
        $options = $definition['provider_options'] ?? [];

        $countries = $this->resolveCountries($options);
        $localeMap = $this->resolveLocaleMap($options);
        $indexBase = $options['index_base'] ?? 'products';
        $limit = max(1, (int) ($params['limit'] ?? $context->getLimit()));
        $threshold = (float) ($params['threshold'] ?? 0);

        $candidates = [];

        $entryId = $context->getEntry()->getKey();

        foreach ($countries as $country) {
            $countryKey = strtolower($country);
            $locale = $localeMap[$countryKey] ?? app()->getLocale();
            $term = $this->resolveSearchTerm($context, $fields, $locale);

            if (! $term) {
                continue;
            }

            $queries = $this->buildQueryVariants($term, $locale);
            if ($queries === []) {
                continue;
            }

            foreach ($queries as $query) {

                $hits = $this->performSearch(
                    $client,
                    $indexBase.'_'.$country,
                    $query,
                    $locale,
                    $entryId,
                    $limit
                );

                foreach ($hits as $hit) {
                    $productId = $hit['id'] ?? null;

                    if (! $productId || (int) $productId === (int) $entryId) {
                        continue;
                    }

                    $candidateValue = $hit['name_'.$locale] ?? ($hit['short_name_'.$locale] ?? null);

                    if (! is_string($candidateValue) || $candidateValue === '') {
                        continue;
                    }

                    $score = $this->similarity($term, $candidateValue);

                    if ($threshold > 0 && $score < $threshold) {
                        continue;
                    }

                    if (! isset($candidates[$productId]) || $candidates[$productId]['score'] < $score) {
                        $candidates[$productId] = [
                            'score' => $score,
                            'meta' => [
                                'source' => 'meilisearch',
                                'country' => $country,
                                'locale' => $locale,
                                'query' => $query,
                            ],
                        ];
                    }
                }
            }
        }

        if ($candidates === []) {
            return $this->fallback($context, $params);
        }

        $ids = array_keys($candidates);
        // $model = $context->getCrud()->model;
        $model = new \Backpack\Store\app\Models\Catalog;
        $keyName = $model->getKeyName();

        $models = $model->newQuery()
        // $models = \Backpack\Store\app\Models\Catalog::
            ->whereIn($keyName, $ids)
            ->get()
            ->keyBy($keyName);

        $results = [];

        foreach ($candidates as $id => $payload) {
            if (! isset($models[$id])) {
                continue;
            }

            $results[] = [
                'model' => $models[$id],
                'score' => $payload['score'],
                'meta' => $payload['meta'],
            ];
        }

        usort($results, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        if ($results === []) {
            return $this->fallback($context, $params);
        }

        return collect($results);
    }

    protected function makeClient(): ?Client
    {
        $host = \Settings::get('dress.search.meilisearch.host', config('dress.search.meilisearch.host'));
        $key = \Settings::get('dress.search.meilisearch.key', config('dress.search.meilisearch.key'));

        if (! $host) {
            return null;
        }

        try {
            return $key ? new Client($host, $key) : new Client($host);
        } catch (\Throwable $e) {
            \Log::warning('Unable to create Meilisearch client: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolveCountries(array $options): array
    {
        $countries = $options['countries'] ?? array_keys(\Store::countries());
        $countries = is_array($countries) ? $countries : [$countries];

        $normalized = [];

        foreach ($countries as $country) {
            if (! is_string($country) || trim($country) === '') {
                continue;
            }

            $normalized[] = trim($country);
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, string>
     */
    protected function resolveLocaleMap(array $options): array
    {
        $map = $options['locale_map'] ?? \Store::defaultCountryLocales();

        if (! is_array($map)) {
            $map = \Store::defaultCountryLocales();
        }

        $normalized = [];

        foreach ($map as $country => $locale) {
            if (! is_string($country) || ! is_string($locale)) {
                continue;
            }

            $normalized[strtolower($country)] = $locale;
        }

        return $normalized;
    }

    /**
     * Try resolving a localized value for the search term.
     */
    protected function resolveSearchTerm(SimilarSearchContext $context, array $fields, string $locale): ?string
    {
        foreach ($fields as $field) {
            $value = $context->resolveFieldValue($field, $locale);

            if ($value !== null) {
                return $value;
            }
        }

        foreach ($fields as $field) {
            $value = $context->resolveFieldValue($field);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function performSearch(Client $client, string $index, string $query, string $locale, $entryId, int $limit): array
    {
        try {
            $options = [
                'limit' => min($limit * 3, 100),
                'attributesToRetrieve' => [
                    'id',
                    'name_'.$locale,
                    'short_name_'.$locale,
                ],
            ];

            $result = $client->index($index)->search($query, $options);

            if ($result instanceof \Meilisearch\Search\SearchResult) {
                return $result->getHits();
            }

            return $result['hits'] ?? [];
        } catch (\Throwable $e) {
            \Log::warning('Meilisearch similar search failed: '.$e->getMessage(), ['index' => $index]);

            return [];
        }
    }

    protected function similarity(string $source, string $candidate): float
    {
        $a = mb_strtolower($source, 'UTF-8');
        $b = mb_strtolower($candidate, 'UTF-8');
        similar_text($a, $b, $percent);

        return (float) $percent;
    }

    protected function fallback(SimilarSearchContext $context, array $params): Collection
    {
        /** @var \Backpack\CRUD\app\Library\ServiceOperation\Similar\Contracts\SimilarSearchProvider $provider */
        $provider = app(DatabaseSimilarSearchProvider::class);

        return $provider->search($context, $params);
    }

    /**
     * Build additional query variants (drop first token, single tokens, etc.).
     *
     * @return array<int, string>
     */
    protected function buildQueryVariants(string $value, string $locale): array
    {
        // Use QueryNormalizer to get variants (transliteration, keyboard fix, etc.)
        $normalizedVariants = app(QueryNormalizer::class)->variants($value, $locale);
        
        // Take the primary variant (usually the first one) for token manipulation
        $primary = $normalizedVariants[0] ?? $value;
        
        $queries = $normalizedVariants;

        $tokens = $this->tokenize($primary);

        if (count($tokens) > 1) {
            $queries[] = implode(' ', array_slice($tokens, 1));
            $queries[] = implode(' ', array_slice($tokens, 0, -1));
        }

        foreach ($tokens as $token) {
            if (mb_strlen($token, 'UTF-8') >= 4) {
                $queries[] = $token;
            }
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * @return array<int, string>
     */
    protected function tokenize(string $value): array
    {
        $normalized = mb_strtolower(trim($value), 'UTF-8');

        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/[\s,.;:!?\-\/]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return $tokens ?: [];
    }
}
