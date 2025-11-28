<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;

class ListEngine
{
    public function __construct(
        protected ListSourceRegistry $registry,
        protected FilterEngine $filterEngine,
        protected SortingEngine $sortingEngine,
        protected AvailabilityGate $availabilityGate,
        protected ProductHydrator $hydrator
    ) {
    }

    public function build(ProductList $list, ListRequestContext $context): array
    {
        $capacity = $this->resolveCapacity($list, $context);
        $definitions = $this->prepareSourceDefinitions($list);

        $groupOrder = [];
        $groupedResults = [];
        foreach ($definitions as $index => $definition) {
            $resolver = $this->registry->get($definition->alias);
            if (!$resolver) {
                continue;
            }

            $result = $resolver->resolve($definition, $list, $context);
            if ($result->isEmpty()) {
                continue;
            }

            $groupKey = $definition->group ?? "__{$index}";
            $groupedResults[$groupKey][] = $result;
            if (!array_key_exists($groupKey, $groupOrder)) {
                $groupOrder[$groupKey] = count($groupOrder);
            }
        }

        $items = [];
        foreach (array_keys($groupOrder) as $groupKey) {
            $bucket = $groupedResults[$groupKey] ?? [];
            $combined = $this->combineGroup($bucket, $capacity);
            foreach ($combined as $item) {
                $items[] = $item;
            }
        }

        $items = $this->deduplicate($items);

        $allowed = $this->availabilityGate->allowedIds(array_map(fn($i) => $i->productId, $items), $context->country);
        $allowedSet = array_fill_keys($allowed, true);
        $items = array_values(array_filter($items, fn($item) => isset($allowedSet[$item->productId])));

        $listFilters = is_array($list->filters) ? $list->filters : [];
        $items = $this->applyListFilters($items, $listFilters, $context);

        $catalogRows = $this->hydrator->fetchCatalogRows(array_map(fn($i) => $i->productId, $items), $context->country);

        $sortOrder = $list->sort_order;
        if (!is_array($sortOrder)) {
            $sortOrder = $sortOrder ? [$sortOrder] : [];
        }

        $items = $this->sortingEngine->sort($items, $sortOrder, $catalogRows, $context);

        $total = count($items);
        $items = array_slice($items, 0, $capacity);

        if ($context->pageNumber !== null && $context->perPage !== null && $context->perPage > 0) {
            $offset = max(0, $context->pageNumber - 1) * $context->perPage;
            $items = array_slice($items, $offset, $context->perPage);
        }

        $hydrated = $this->hydrator->hydrate(array_map(fn($i) => $i->productId, $items), $context->country, $context->lang);

        return [
            'items' => $hydrated,
            'meta' => [
                'total' => $total,
                'capacity' => $capacity,
                'page' => $context->pageNumber,
                'per_page' => $context->perPage,
            ],
        ];
    }

    protected function resolveCapacity(ProductList $list, ListRequestContext $context): int
    {
        $base = (int) ($list->capacity ?? 12);
        if ($context->capacityOverride !== null) {
            return min($base, max(0, $context->capacityOverride));
        }
        return $base;
    }

    /**
     * @return SourceDefinition[]
     */
    protected function prepareSourceDefinitions(ProductList $list): array
    {
        $sources = $list->sources;
        if (!is_array($sources)) {
            return [];
        }

        $definitions = [];
        foreach ($sources as $source) {
            if (is_string($source)) {
                $source = ['alias' => $source];
            }
            if (!is_array($source)) {
                continue;
            }
            try {
                $definitions[] = new SourceDefinition($source);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $definitions;
    }

    /**
     * @param  SourceResult[] $bucket
     * @return ResolvedItem[]
     */
    protected function combineGroup(array $bucket, int $capacity): array
    {
        if (empty($bucket)) {
            return [];
        }

        $bucket = array_map(function (SourceResult $result) {
            $items = array_map(fn($item) => $this->withSourceMeta($item, $result->definition->alias), $result->items);
            return new SourceResult($result->definition, $items, $result->meta);
        }, $bucket);

        if (count($bucket) === 1) {
            return array_slice($bucket[0]->items, 0, $capacity);
        }

        $firstItems = $bucket[0]->items;
        $sets = [];
        foreach ($bucket as $result) {
            $sets[] = array_map(fn($item) => $item->productId, $result->items);
        }

        $intersection = $this->intersectOrdered($sets, $firstItems);
        if (!empty($intersection)) {
            return array_slice($intersection, 0, $capacity);
        }

        // fallback to union preserving order by bucket priority
        $union = [];
        $seen = [];
        foreach ($bucket as $result) {
            foreach ($result->items as $item) {
                if (isset($seen[$item->productId])) {
                    continue;
                }
                $union[] = $item;
                $seen[$item->productId] = true;
                if (count($union) >= $capacity) {
                    break 2;
                }
            }
        }

        return $union;
    }

    /**
     * @param  array<int, int[]> $sets
     * @param  ResolvedItem[] $orderedItems
     * @return ResolvedItem[]
     */
    protected function intersectOrdered(array $sets, array $orderedItems): array
    {
        if (count($sets) < 2) {
            return [];
        }

        $required = [];
        foreach ($sets as $index => $set) {
            if ($index === 0) {
                continue;
            }
            foreach ($set as $id) {
                $required[$index][$id] = true;
            }
        }

        $result = [];
        foreach ($orderedItems as $item) {
            $include = true;
            foreach ($required as $set) {
                if (!isset($set[$item->productId])) {
                    $include = false;
                    break;
                }
            }
            if ($include) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  ResolvedItem[] $items
     * @return ResolvedItem[]
     */
    protected function deduplicate(array $items): array
    {
        $seen = [];
        $unique = [];
        foreach ($items as $item) {
            if (isset($seen[$item->productId])) {
                continue;
            }
            $unique[] = $item;
            $seen[$item->productId] = true;
        }
        return $unique;
    }

    /**
     * @param  ResolvedItem[] $items
     * @return ResolvedItem[]
     */
    protected function applyListFilters(array $items, array $filters, ListRequestContext $context): array
    {
        if (empty($filters)) {
            return $items;
        }

        $filterable = [];
        $indices = [];
        foreach ($items as $index => $item) {
            if (!empty($item->meta['is_manual'])) {
                continue;
            }
            $filterable[] = $item;
            $indices[] = $index;
        }

        if (empty($filterable)) {
            return $items;
        }

        $filtered = $this->filterEngine->apply($filterable, $filters, $context);
        $keepMap = [];
        foreach ($filtered as $item) {
            $keepMap[$item->productId] = $item;
        }

        $result = [];
        foreach ($items as $item) {
            if (!empty($item->meta['is_manual'])) {
                $result[] = $item;
                continue;
            }
            if (isset($keepMap[$item->productId])) {
                $result[] = $keepMap[$item->productId];
            }
        }

        return $result;
    }

    protected function withSourceMeta(ResolvedItem $item, string $alias): ResolvedItem
    {
        if (($item->meta['source'] ?? null) === $alias) {
            return $item;
        }

        $meta = ['source' => $alias] + $item->meta;
        return new ResolvedItem($item->productId, $meta);
    }
}
