<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FilterEngine
{
    protected ?bool $catalogHasVisible = null;

    /**
     * @param  ResolvedItem[] $items
     * @param  array          $filters
     * @return ResolvedItem[]
     */
    public function apply(array $items, array $filters, ListRequestContext $context): array
    {
        if (empty($filters) || empty($items)) {
            return $items;
        }

        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item->productId] = $item;
        }

        foreach ($filters as $filter) {
            $normalized = $this->normalizeFilter($filter);
            if (!$normalized) {
                continue;
            }

            $currentIds = array_keys($indexed);
            $matching = $this->collectMatchingIds($normalized, $context, $currentIds);
            if ($matching === null) {
                continue;
            }

            $matchingSet = array_fill_keys($matching, true);

            if ($normalized['direction'] === 'include') {
                $indexed = array_filter($indexed, fn($item) => isset($matchingSet[$item->productId]));
            } else {
                $indexed = array_filter($indexed, fn($item) => !isset($matchingSet[$item->productId]));
            }
        }

        return array_values($indexed);
    }

    /**
     * Подбор набора продуктов только на основании фильтров (используется, напр., базовым источником).
     *
     * @return ResolvedItem[]
     */
    public function collect(array $filters, ListRequestContext $context, int $limit): array
    {
        $limit = max($limit, 0);
        if ($limit === 0) {
            return [];
        }

        $normalized = [];
        foreach ($filters as $filter) {
            $f = $this->normalizeFilter($filter);
            if ($f) {
                $normalized[] = $f;
            }
        }

        $includeSets = [];
        foreach ($normalized as $filter) {
            if ($filter['direction'] !== 'include') {
                continue;
            }
            $ids = $this->collectMatchingIds($filter, $context, null, $limit * 8);
            if ($ids === null) {
                continue;
            }
            $includeSets[] = $ids;
        }

        if (!empty($includeSets)) {
            $candidates = array_shift($includeSets);
            foreach ($includeSets as $set) {
                $candidates = array_values(array_intersect($candidates, $set));
            }
        } else {
            $candidates = $this->pullAvailableIds($context, $limit * 8);
        }

        if (empty($candidates)) {
            return [];
        }

        $candidates = array_values(array_unique($candidates));

        foreach ($normalized as $filter) {
            if ($filter['direction'] !== 'exclude') {
                continue;
            }
            $excludeIds = $this->collectMatchingIds($filter, $context, $candidates);
            if (empty($excludeIds)) {
                continue;
            }
            $excludeSet = array_fill_keys($excludeIds, true);
            $candidates = array_values(array_filter($candidates, fn($id) => !isset($excludeSet[$id])));
            if (!$candidates) {
                break;
            }
        }

        $candidates = array_slice($candidates, 0, $limit);

        return array_map(fn($id) => new ResolvedItem($id), $candidates);
    }

    protected function normalizeFilter(array $filter): ?array
    {
        $type = $filter['type'] ?? $filter['key'] ?? null;
        if (!is_string($type) || $type === '') {
            return null;
        }
        $direction = strtolower((string) ($filter['direction'] ?? 'include'));
        if (!in_array($direction, ['include', 'exclude'], true)) {
            $direction = 'include';
        }
        $filter['type'] = $type;
        $filter['direction'] = $direction;
        return $filter;
    }

    protected function collectMatchingIds(array $filter, ListRequestContext $context, ?array $restrict = null, ?int $limit = null): ?array
    {
        return match ($filter['type']) {
            'categories' => $this->filterByCategories($filter, $context, $restrict, $limit),
            'brands', 'brand' => $this->filterByBrands($filter, $context, $restrict, $limit),
            'tags', 'tag' => $this->filterByTags($filter, $context, $restrict, $limit),
            'attributes', 'attribute' => $this->filterByAttributes($filter, $context, $restrict, $limit),
            'price_range', 'price' => $this->filterByPrice($filter, $context, $restrict, $limit),
            'stock' => $this->filterByStock($filter, $context, $restrict, $limit),
            'sale', 'discount' => $this->filterBySale($filter, $context, $restrict, $limit),
            'products', 'product' => $this->filterByProducts($filter, $restrict),
            'orders' => $this->filterByOrders($filter, $context, $restrict, $limit),
            default => null,
        };
    }

    protected function catalogBaseQuery(ListRequestContext $context): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('ak_catalog as c')
            ->select('c.product_id')
            ->where('c.country_code', $context->country);

        $query->where('c.is_available', 1);

        return $query;
    }

    protected function pullAvailableIds(ListRequestContext $context, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        return $this->catalogBaseQuery($context)
            ->orderBy('c.product_id')
            ->limit($limit)
            ->pluck('c.product_id')
            ->toArray();
    }

    protected function filterByCategories(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $categories = $filter['categories[]'] ?? $filter['ids'] ?? null;
        if (!$categories) {
            return null;
        }
        $includeChildren = (bool) ($filter['include_children'] ?? false);
        $categoryIds = $this->expandCategoryIds((array) $categories, $includeChildren);
        if (!$categoryIds) {
            return [];
        }

        $query = $this->catalogBaseQuery($context)
            ->where(function ($q) use ($categoryIds) {
                foreach ($categoryIds as $id) {
                    $q->orWhereJsonContains('c.category_ids', (int)$id);
                }
            });

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByBrands(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $brands = $filter['brands[]'] ?? $filter['ids'] ?? null;
        if (!$brands) {
            return null;
        }

        $brandIds = array_values(array_unique(array_map('intval', (array)$brands)));
        if (!$brandIds) {
            return [];
        }

        $query = $this->catalogBaseQuery($context)
            ->whereIn('c.brand_id', $brandIds);

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByTags(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $tags = $filter['tags[]'] ?? $filter['ids'] ?? null;
        if (!$tags) {
            return null;
        }

        $tagIds = array_values(array_unique(array_map('intval', (array)$tags)));
        if (!$tagIds) {
            return [];
        }

        $query = DB::table('ak_product_tag as t')
            ->join('ak_catalog as c', function ($join) use ($context) {
                $join->on('c.product_id', '=', 't.product_id')
                    ->where('c.country_code', '=', $context->country);
                if ($this->catalogHasVisible()) {
                    $join->where('c.is_visible', '=', 1);
                } else {
                    $join->where('c.is_available', '=', 1);
                }
            })
            ->whereIn('t.tag_id', $tagIds)
            ->select('c.product_id');

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByAttributes(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $rules = $filter['rules'] ?? [];
        if (!is_array($rules) || empty($rules)) {
            return null;
        }

        $logic = strtoupper((string) ($filter['logic'] ?? 'AND')) === 'OR' ? 'OR' : 'AND';

        $query = DB::table('ak_catalog_attr as ca')
            ->join('ak_catalog as c', function ($join) use ($context) {
                $join->on('ca.group_id', '=', 'c.group_id')
                     ->where('c.country_code', '=', $context->country);
                if ($this->catalogHasVisible()) {
                    $join->where('c.is_visible', '=', 1);
                } else {
                    $join->where('c.is_available', '=', 1);
                }
            })
            ->where('ca.country_code', $context->country)
            ->select('c.product_id');

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }

        $query->where(function ($outer) use ($rules, $logic) {
            foreach ($rules as $rule) {
                $outer->{$logic === 'AND' ? 'where' : 'orWhere'}(function ($inner) use ($rule) {
                    $attributeId = (int) ($rule['attribute'] ?? 0);
                    if ($attributeId <= 0) {
                        $inner->whereRaw('1=0');
                        return;
                    }
                    $inner->where('ca.attribute_id', $attributeId);
                    $operator = strtoupper((string) ($rule['operator'] ?? 'IN'));
                    $values = $this->parseValues($rule['values'] ?? null);
                    if (in_array($operator, ['IN', 'EQ'], true)) {
                        if (!$values) {
                            $inner->whereRaw('1=0');
                            return;
                        }
                        $inner->whereIn('ca.attribute_value_id', $values);
                    } elseif ($operator === 'GTE') {
                        $value = $values[0] ?? null;
                        if ($value === null) {
                            $inner->whereRaw('1=0');
                            return;
                        }
                        $inner->where('ca.value', '>=', (float)$value);
                    } elseif ($operator === 'LTE') {
                        $value = $values[0] ?? null;
                        if ($value === null) {
                            $inner->whereRaw('1=0');
                            return;
                        }
                        $inner->where('ca.value', '<=', (float)$value);
                    }
                });
            }
        });

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByPrice(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $min = $filter['price_min'] ?? $filter['min'] ?? null;
        $max = $filter['price_max'] ?? $filter['max'] ?? null;
        if ($min === null && $max === null) {
            return null;
        }

        $query = $this->catalogBaseQuery($context);
        if ($min !== null) {
            $query->where('c.price', '>=', (float)$min);
        }
        if ($max !== null) {
            $query->where('c.price', '<=', (float)$max);
        }

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByStock(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $onlyInStock = (bool) ($filter['only_in_stock'] ?? false);
        $stockMin = $filter['stock_min'] ?? null;
        if (!$onlyInStock && $stockMin === null) {
            return null;
        }

        $query = $this->catalogBaseQuery($context);
        if ($onlyInStock) {
            $query->where('c.in_stock', '>', 0);
        }
        if ($stockMin !== null) {
            $query->where('c.in_stock', '>=', (int) $stockMin);
        }

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterBySale(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $minDiscount = $filter['min_discount_percent'] ?? $filter['min_percent'] ?? null;
        $requireOldPrice = (bool) ($filter['require_old_price'] ?? false);
        if ($minDiscount === null && !$requireOldPrice) {
            return null;
        }

        $query = $this->catalogBaseQuery($context)
            ->whereNotNull('c.old_price')
            ->where('c.old_price', '>', 0);

        if ($minDiscount !== null) {
            $min = max(0.0, (float)$minDiscount);
            $query->whereRaw('((c.old_price - c.price) / c.old_price) * 100 >= ?', [$min]);
        }

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }
        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function filterByProducts(array $filter, ?array $restrict): ?array
    {
        $ids = $filter['include_product_ids[]'] ?? $filter['product_ids'] ?? $filter['ids'] ?? null;

        if (!$ids) {
            return null;
        }

        $productIds = array_values(array_unique(array_map('intval', (array)$ids)));
        if (!$productIds) {
            return [];
        }

        if ($restrict) {
            $productIds = array_values(array_intersect($productIds, $restrict));
        }

        return $productIds;
    }

    protected function filterByOrders(array $filter, ListRequestContext $context, ?array $restrict, ?int $limit): ?array
    {
        $minCount = $filter['min_count'] ?? $filter['min_orders'] ?? null;
        $minCount = max(1, (int) ($minCount ?? 1));

        $metric = $filter['metric'] ?? 'orders';
        $metric = in_array($metric, ['orders', 'quantity'], true) ? $metric : 'orders';

        $periodDays = max(0, (int) ($filter['period_days'] ?? 0));
        $scope = $filter['scope'] ?? 'current_country';

        $query = $this->catalogBaseQuery($context)
            ->join('ak_order_product as op', 'op.product_id', '=', 'c.product_id');

        if ($scope !== 'global') {
            $query->where('op.country_code', '=', $context->country);
        }

        if ($periodDays > 0) {
            $since = now()->subDays($periodDays);
            $query->join('ak_orders as o', 'o.id', '=', 'op.order_id')
                ->where('o.created_at', '>=', $since);
        }

        if ($restrict) {
            $query->whereIn('c.product_id', $restrict);
        }

        $metricSelect = $metric === 'quantity'
            ? 'COALESCE(SUM(op.amount), 0)'
            : 'COUNT(DISTINCT op.order_id)';

        $query->select('c.product_id')
            ->selectRaw($metricSelect.' as metric_value')
            ->groupBy('c.product_id')
            ->having('metric_value', '>=', $minCount)
            ->orderByDesc('metric_value');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->pluck('c.product_id')->toArray();
    }

    protected function expandCategoryIds(array $ids, bool $includeChildren): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$includeChildren || empty($ids)) {
            return $ids;
        }

        $rows = DB::table('ak_product_categories')
            ->select('id', 'lft', 'rgt')
            ->whereIn('id', $ids)
            ->get();

        if ($rows->isEmpty()) {
            return $ids;
        }

        $extra = [];
        foreach ($rows as $row) {
            if ($row->lft === 0 || $row->rgt === 0) {
                continue;
            }
            $children = DB::table('ak_product_categories')
                ->where('lft', '>=', $row->lft)
                ->where('rgt', '<=', $row->rgt)
                // ->where('parent_id', $row->id)
                ->pluck('id')
                ->toArray();
            $extra = array_merge($extra, $children);
        }

        return array_values(array_unique(array_merge($ids, $extra)));
    }

    protected function parseValues(mixed $raw): array
    {
        if ($raw === null) {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter($raw, fn($v) => $v !== null && $v !== ''));
        }
        if (is_string($raw)) {
            $parts = array_map('trim', explode(',', $raw));
            return array_values(array_filter($parts, fn($v) => $v !== ''));
        }
        return [(string)$raw];
    }
}
