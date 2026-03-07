<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Illuminate\Support\Facades\DB;


class SortingEngine
{
    /**
     * @param  ResolvedItem[] $items
     * @param  array          $sortOrder
     * @param  array          $catalogRows keyed by product_id
     * @param  ListRequestContext $context
     * @return ResolvedItem[]
     */
    public function sort(array $items, array $sortOrder, array $catalogRows, ListRequestContext $context): array
    {
        if (empty($items)) {
            return $items;
        }

        $normalized = $this->normalizeSortOrder($sortOrder);
        if (empty($normalized)) {
            return $items;
        }

        $orderStats = $this->needsOrderStats($normalized)
            ? $this->fetchOrderStats($items, $context)
            : [];

        if (in_array('random', array_column($normalized, 'criterion'), true)) {
            shuffle($items);
            return $items;
        }

        $scored = [];
        foreach ($items as $index => $item) {
            $scored[] = ['item' => $item, 'index' => $index];
        }

        usort($scored, function ($left, $right) use ($normalized, $catalogRows, $orderStats) {
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
                } elseif ($criterion === 'orders_count') {
                    $cmp = $this->compareOrderCount($a->productId, $b->productId, $orderStats, $direction);
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
                $shortcut = $this->mapShortcut($entry);
                if ($shortcut) {
                    $normalized[] = $shortcut;
                }
            } elseif (is_array($entry) && !empty($entry['criterion'])) {
                $criterion = (string) $entry['criterion'];
                $shortcut = $this->mapShortcut($criterion) ?? ['criterion' => $criterion, 'direction' => 'asc'];
                $direction = strtolower((string) ($entry['direction'] ?? $shortcut['direction'] ?? 'asc'));
                if (!in_array($direction, ['asc', 'desc'], true)) {
                    $direction = $shortcut['direction'] ?? 'asc';
                }
                $shortcut['direction'] = $direction;
                $normalized[] = $shortcut;
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
            'relevance', 'source' => ['criterion' => 'relevance', 'direction' => 'asc'],
            'random' => ['criterion' => 'random', 'direction' => 'asc'],
            'orders', 'orders_desc', 'orders_count' => ['criterion' => 'orders_count', 'direction' => 'desc'],
            'orders_asc' => ['criterion' => 'orders_count', 'direction' => 'asc'],
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

    protected function compareOrderCount(int $aId, int $bId, array $orderStats, string $direction): int
    {
        $a = $orderStats[$aId] ?? null;
        $b = $orderStats[$bId] ?? null;

        $aCount = (int) ($a['orders'] ?? 0);
        $bCount = (int) ($b['orders'] ?? 0);

        $cmp = $aCount <=> $bCount;
        if ($cmp !== 0) {
            return $direction === 'desc' ? -$cmp : $cmp;
        }

        $aLastOrder = (int) ($a['last_order_id'] ?? 0);
        $bLastOrder = (int) ($b['last_order_id'] ?? 0);
        $cmp = $bLastOrder <=> $aLastOrder;
        if ($cmp !== 0) {
            return $cmp;
        }

        $aQty = (int) ($a['quantity'] ?? 0);
        $bQty = (int) ($b['quantity'] ?? 0);
        $cmp = $bQty <=> $aQty;
        if ($cmp !== 0) {
            return $cmp;
        }

        return $aId <=> $bId;
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

    protected function needsOrderStats(array $normalized): bool
    {
        foreach ($normalized as $sort) {
            if (($sort['criterion'] ?? null) === 'orders_count') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param  ResolvedItem[] $items
     */
    protected function fetchOrderStats(array $items, ListRequestContext $context): array
    {
        $ids = array_values(array_unique(array_map(fn($item) => $item->productId, $items)));
        if (empty($ids)) {
            return [];
        }

        return DB::table('ak_order_product as op')
            ->whereIn('op.product_id', $ids)
            ->where('op.country_code', '=', $context->country)
            ->selectRaw('op.product_id')
            ->selectRaw('COUNT(DISTINCT op.order_id) as orders')
            ->selectRaw('COALESCE(SUM(op.amount), 0) as quantity')
            ->selectRaw('MAX(op.order_id) as last_order_id')
            ->groupBy('op.product_id')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (int) $row->product_id => [
                        'orders' => (int) ($row->orders ?? 0),
                        'quantity' => (int) ($row->quantity ?? 0),
                        'last_order_id' => (int) ($row->last_order_id ?? 0),
                    ],
                ];
            })
            ->toArray();
    }
}
