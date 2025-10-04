<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;

class SortingEngine
{
    /**
     * @param  ResolvedItem[] $items
     * @param  array          $sortOrder
     * @param  array          $catalogRows keyed by product_id
     * @return ResolvedItem[]
     */
    public function sort(array $items, array $sortOrder, array $catalogRows): array
    {
        if (empty($items)) {
            return $items;
        }

        $normalized = $this->normalizeSortOrder($sortOrder);
        if (empty($normalized)) {
            return $items;
        }

        if (in_array('random', array_column($normalized, 'criterion'), true)) {
            shuffle($items);
            return $items;
        }

        $scored = [];
        foreach ($items as $index => $item) {
            $scored[] = ['item' => $item, 'index' => $index];
        }

        usort($scored, function ($left, $right) use ($normalized, $catalogRows) {
            /** @var ResolvedItem $a */
            $a = $left['item'];
            /** @var ResolvedItem $b */
            $b = $right['item'];

            foreach ($normalized as $sort) {
                $criterion = $sort['criterion'];
                $direction = $sort['direction'];

                $cmp = 0;
                if ($criterion === 'discount_first') {
                    $cmp = $this->compareDiscount($a->productId, $b->productId, $catalogRows);
                } elseif ($criterion === 'price') {
                    $cmp = $this->comparePrice($a->productId, $b->productId, $catalogRows);
                } elseif ($criterion === 'relevance') {
                    continue;
                }

                if ($cmp !== 0) {
                    if ($criterion === 'price') {
                        return $direction === 'desc' ? -$cmp : $cmp;
                    }
                    return $cmp;
                }
            }

            return $left['index'] <=> $right['index'];
        });

        return array_column($scored, 'item');
    }

    protected function normalizeSortOrder(array $sortOrder): array
    {
        $normalized = [];
        foreach ($sortOrder as $entry) {
            if (is_string($entry)) {
                $normalized[] = $this->mapShortcut($entry);
            } elseif (is_array($entry) && !empty($entry['criterion'])) {
                $normalized[] = [
                    'criterion' => (string) $entry['criterion'],
                    'direction' => strtolower((string) ($entry['direction'] ?? 'asc')),
                ];
            }
        }

        return array_values(array_filter($normalized, fn($s) => $s !== null));
    }

    protected function mapShortcut(string $value): ?array
    {
        $value = strtolower($value);
        return match ($value) {
            'discount_first' => ['criterion' => 'discount_first', 'direction' => 'desc'],
            'price', 'price_asc' => ['criterion' => 'price', 'direction' => 'asc'],
            'price_desc' => ['criterion' => 'price', 'direction' => 'desc'],
            'relevance' => ['criterion' => 'relevance', 'direction' => 'asc'],
            'random' => ['criterion' => 'random', 'direction' => 'asc'],
            default => null,
        };
    }

    protected function compareDiscount(int $aId, int $bId, array $catalogRows): int
    {
        $a = $catalogRows[$aId] ?? null;
        $b = $catalogRows[$bId] ?? null;

        $aDiscount = $this->hasDiscount($a) ? 1 : 0;
        $bDiscount = $this->hasDiscount($b) ? 1 : 0;

        return $bDiscount <=> $aDiscount;
    }

    protected function comparePrice(int $aId, int $bId, array $catalogRows): int
    {
        $a = $catalogRows[$aId]->price ?? null;
        $b = $catalogRows[$bId]->price ?? null;

        if ($a === null && $b === null) {
            return 0;
        }
        if ($a === null) {
            return 1;
        }
        if ($b === null) {
            return -1;
        }

        return $a <=> $b;
    }

    protected function hasDiscount($row): bool
    {
        if (!$row) {
            return false;
        }
        $price = $row->price ?? null;
        $old = $row->old_price ?? null;
        if ($price === null || $old === null) {
            return false;
        }
        return (float) $old > (float) $price;
    }
}
