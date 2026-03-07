<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\SortingEngine;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use PHPUnit\Framework\TestCase;

class SortingEngineOrderTieBreakTest extends TestCase
{
    public function test_orders_sort_uses_recent_order_as_tie_breaker(): void
    {
        $engine = new class extends SortingEngine {
            protected function fetchOrderStats(array $items, ListRequestContext $context): array
            {
                return [
                    253 => ['orders' => 2, 'quantity' => 2, 'last_order_id' => 57],
                    469 => ['orders' => 1, 'quantity' => 1, 'last_order_id' => 61],
                    470 => ['orders' => 1, 'quantity' => 1, 'last_order_id' => 60],
                    472 => ['orders' => 1, 'quantity' => 20, 'last_order_id' => 62],
                ];
            }
        };

        $items = [new ResolvedItem(469), new ResolvedItem(470), new ResolvedItem(472), new ResolvedItem(253)];
        $context = new ListRequestContext(page: 'homepage', country: 'cz', lang: 'cs');

        $asc = $engine->sort($items, [['criterion' => 'orders_count', 'direction' => 'asc']], [], $context);
        $ascIds = array_map(fn(ResolvedItem $item): int => $item->productId, $asc);
        $this->assertSame([472, 469, 470, 253], $ascIds);

        $desc = $engine->sort($items, [['criterion' => 'orders_count', 'direction' => 'desc']], [], $context);
        $descIds = array_map(fn(ResolvedItem $item): int => $item->productId, $desc);
        $this->assertSame([253, 472, 469, 470], $descIds);
    }
}
