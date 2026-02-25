<?php

namespace Backpack\Store\app\Services\Product;

use Backpack\Store\app\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProductManualSortCacheService
{
    private const CACHE_KEY = 'store:product:manual-sort:v1';

    public function getSnapshot(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return $this->buildSnapshot();
        });
    }

    public function rebuild(): array
    {
        $snapshot = $this->buildSnapshot();
        Cache::forever(self::CACHE_KEY, $snapshot);

        return $snapshot;
    }

    public function getStatsForProduct(?Product $product = null): array
    {
        $snapshot = $this->getSnapshot();
        $currentValue = $product && $product->manual_sort !== null
            ? (float) $product->manual_sort
            : null;

        $globalValues = $this->extractValues($snapshot['global']['values_by_product'] ?? []);
        $globalStats = [
            'values' => $globalValues,
            'min' => $globalValues !== [] ? min($globalValues) : null,
            'max' => $globalValues !== [] ? max($globalValues) : null,
            'current_value' => $currentValue,
            'current_position' => $this->resolvePosition($globalValues, $currentValue),
        ];

        $categoryStats = [];
        $categoryScopes = $this->collectCategoryScopes($product);

        foreach ($categoryScopes as $categoryId => $categoryName) {
            $rows = $snapshot['categories'][$categoryId]['values_by_product'] ?? [];
            $values = $this->extractValues($rows);

            $categoryStats[] = [
                'id' => $categoryId,
                'name' => $categoryName ?: ($snapshot['categories'][$categoryId]['name'] ?? ('#'.$categoryId)),
                'values' => $values,
                'min' => $values !== [] ? min($values) : null,
                'max' => $values !== [] ? max($values) : null,
                'current_value' => $currentValue,
                'current_position' => $this->resolvePosition($values, $currentValue),
            ];
        }

        return [
            'updated_at' => $snapshot['updated_at'] ?? null,
            'global' => $globalStats,
            'categories' => $categoryStats,
        ];
    }

    protected function buildSnapshot(): array
    {
        $globalValues = DB::table('ak_products')
            ->whereNotNull('manual_sort')
            ->pluck('manual_sort', 'id')
            ->map(function ($value) {
                return (float) $value;
            })
            ->all();

        $categories = [];
        $categoryTree = DB::table('ak_product_categories')
            ->select(['id', 'parent_id', 'name'])
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->id => [
                        'parent_id' => $row->parent_id ? (int) $row->parent_id : null,
                        'name' => $this->decodeCategoryName($row->name ?? null),
                    ],
                ];
            })
            ->all();

        $rows = DB::table('ak_category_product as cp')
            ->join('ak_products as p', 'p.id', '=', 'cp.product_id')
            ->whereNotNull('p.manual_sort')
            ->select([
                'cp.category_id',
                'cp.product_id',
                'p.manual_sort',
            ])
            ->get();

        foreach ($rows as $row) {
            $categoryId = (int) ($row->category_id ?? 0);
            $productId = (int) ($row->product_id ?? 0);

            if ($categoryId <= 0 || $productId <= 0) {
                continue;
            }

            $value = (float) $row->manual_sort;
            $scopeIds = $this->categoryScopeIds($categoryId, $categoryTree);

            foreach ($scopeIds as $scopeId) {
                if (!isset($categories[$scopeId])) {
                    $categories[$scopeId] = [
                        'name' => $categoryTree[$scopeId]['name'] ?? ('#'.$scopeId),
                        'values_by_product' => [],
                    ];
                }

                if ($categories[$scopeId]['name'] === '') {
                    $categories[$scopeId]['name'] = $categoryTree[$scopeId]['name'] ?? ('#'.$scopeId);
                }

                $categories[$scopeId]['values_by_product'][$productId] = $value;
            }
        }

        return [
            'updated_at' => now()->toDateTimeString(),
            'global' => [
                'values_by_product' => $globalValues,
            ],
            'categories' => $categories,
        ];
    }

    protected function categoryScopeIds(int $categoryId, array $categoryTree): array
    {
        $ids = [];
        $visited = [];
        $current = $categoryId;

        while ($current > 0 && !isset($visited[$current])) {
            $visited[$current] = true;
            $ids[] = $current;
            $current = (int) ($categoryTree[$current]['parent_id'] ?? 0);
        }

        return $ids;
    }

    protected function extractValues(array $valuesByProduct, ?int $excludeProductId = null): array
    {
        $normalized = [];

        foreach ($valuesByProduct as $productId => $value) {
            if ($excludeProductId !== null && (int) $productId === $excludeProductId) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $float = (float) $value;
            $normalized[(string) $float] = $float;
        }

        $values = array_values($normalized);
        rsort($values, SORT_NUMERIC);

        return $values;
    }

    protected function resolvePosition(array $values, ?float $currentValue): ?int
    {
        if ($currentValue === null || $values === []) {
            return null;
        }

        foreach (array_values($values) as $index => $value) {
            if ((float) $value === $currentValue) {
                return $index + 1;
            }
        }

        return null;
    }

    protected function collectCategoryScopes(?Product $product): array
    {
        if (!$product) {
            return [];
        }

        $scopes = [];
        $categories = $product->categories ?? collect();

        foreach ($categories as $category) {
            if (!$category || empty($category->id)) {
                continue;
            }

            $scopes[(int) $category->id] = (string) ($category->name ?? '');

            if (!method_exists($category, 'getParentNode')) {
                continue;
            }

            $parents = $category->getParentNode($category);

            if (!($parents instanceof \Illuminate\Support\Collection)) {
                continue;
            }

            foreach ($parents->reverse() as $parentCategory) {
                if (!$parentCategory || empty($parentCategory->id)) {
                    continue;
                }

                $scopes[(int) $parentCategory->id] = (string) ($parentCategory->name ?? '');
            }
        }

        return $scopes;
    }

    protected function decodeCategoryName($raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_array($raw)) {
            $name = $this->pickTranslatedValue($raw);
            return is_string($name) ? $name : '';
        }

        if (!is_string($raw)) {
            return (string) $raw;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $trimmed;
        }

        $name = $this->pickTranslatedValue($decoded);
        return is_string($name) ? $name : '';
    }

    protected function pickTranslatedValue(array $translations): ?string
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $candidates = array_filter([$locale, $fallback]);

        foreach ($candidates as $code) {
            if (!empty($translations[$code])) {
                return (string) $translations[$code];
            }
        }

        foreach ($translations as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
