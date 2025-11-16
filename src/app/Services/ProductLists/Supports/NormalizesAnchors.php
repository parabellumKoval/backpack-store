<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;

trait NormalizesAnchors
{
    /**
     * @param  int[] $productIds
     * @return array<int,int>
     */
    protected function resolveBaseProductMap(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $products = Product::query()
            ->select('id', 'parent_id')
            ->whereIn('id', $productIds)
            ->get();

        $map = [];
        foreach ($products as $product) {
            $map[$product->id] = $product->parent_id ? (int) $product->parent_id : (int) $product->id;
        }

        return $map;
    }

    /**
     * @param  int[] $ids
     * @param  array<int,int> $baseMap
     * @return int[]
     */
    protected function mapIdsToBase(array $ids, array $baseMap): array
    {
        $result = [];
        foreach ($ids as $id) {
            $result[] = $this->baseIdFor($id, $baseMap);
        }

        return array_values(array_unique($result));
    }

    /**
     * @param  int[] $baseIds
     * @return array<int,true>
     */
    protected function baseLookup(array $baseIds): array
    {
        if (empty($baseIds)) {
            return [];
        }

        return array_fill_keys($baseIds, true);
    }

    /**
     * @param  array<int,int> $baseMap
     */
    protected function baseIdFor(int $productId, array $baseMap): int
    {
        return $baseMap[$productId] ?? $productId;
    }

    /**
     * @param  \Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem[] $items
     * @param  array<int,true> $anchorBaseLookup
     * @return \Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem[]
     */
    protected function removeAnchorsFromItems(array $items, array $anchorBaseLookup): array
    {
        if (empty($items) || empty($anchorBaseLookup)) {
            return $items;
        }

        $productIds = [];
        foreach ($items as $item) {
            if ($item instanceof ResolvedItem) {
                $productIds[] = $item->productId;
            }
        }

        if (empty($productIds)) {
            return $items;
        }

        $baseMap = $this->resolveBaseProductMap($productIds);

        return array_values(array_filter($items, function ($item) use ($baseMap, $anchorBaseLookup) {
            if (!$item instanceof ResolvedItem) {
                return true;
            }
            $baseId = $baseMap[$item->productId] ?? $item->productId;
            return !isset($anchorBaseLookup[$baseId]);
        }));
    }
}
