<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use PHPUnit\Framework\TestCase;

class FilterEngineCollectWindowTest extends TestCase
{
    public function test_collect_intersects_include_filters_without_truncating_secondary_filter_pool(): void
    {
        $engine = new class extends FilterEngine {
            protected function collectMatchingIds(array $filter, ListRequestContext $context, ?array $restrict = null, ?int $limit = null): ?array
            {
                $type = $filter['type'] ?? null;

                $ids = match ($type) {
                    'orders' => range(472, 900),
                    'stock' => range(1, 700),
                    default => null,
                };

                if ($ids === null) {
                    return null;
                }

                if (is_array($restrict)) {
                    $restrictSet = array_fill_keys($restrict, true);
                    $ids = array_values(array_filter($ids, fn(int $id): bool => isset($restrictSet[$id])));
                }

                if ($limit !== null) {
                    $ids = array_slice($ids, 0, $limit);
                }

                return $ids;
            }
        };

        $context = new ListRequestContext(page: 'homepage', country: 'cz', lang: 'cs');

        $items = $engine->collect([
            ['type' => 'orders', 'direction' => 'include', 'min_count' => 1],
            ['type' => 'stock', 'direction' => 'include', 'only_in_stock' => true],
        ], $context, 24);

        $ids = array_map(fn($item) => $item->productId, $items);

        $this->assertCount(24, $ids);
        $this->assertSame(472, $ids[0]);
    }
}
