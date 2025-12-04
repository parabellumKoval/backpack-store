<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;

class ManualListItemsResolver implements SourceResolver
{
    public function supports(string $alias): bool
    {
        return $alias === 'manual_list_items';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $items = $this->itemsFromConfig($definition);
        if (empty($items)) {
            return new SourceResult($definition, []);
        }

        $items = $this->filterByCatalogAvailability($items, $context->country);

        $limit = (int) ($definition->param('limit') ?? $definition->param('capacity') ?? 0);
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        return new SourceResult($definition, $items);
    }

    protected function itemsFromConfig(SourceDefinition $definition): array
    {
        $rawItems = $definition->param('items', []);
        if (!is_array($rawItems) || empty($rawItems)) {
            return [];
        }

        $normalized = [];
        foreach ($rawItems as $row) {
            $productId = is_array($row) ? ($row['product_id'] ?? null) : null;
            if (!is_numeric($productId)) {
                continue;
            }
            $priority = is_array($row) ? ($row['priority'] ?? 0) : 0;
            $normalized[] = [
                'product_id' => (int) $productId,
                'priority' => (int) $priority,
            ];
        }

        usort($normalized, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return array_map(
            fn($row) => new ResolvedItem($row['product_id'], [
                'is_manual' => true,
                'priority' => $row['priority'],
            ]),
            $normalized
        );
    }

    protected function filterByCatalogAvailability(array $items, string $country): array
    {
        $productIds = array_values(array_unique(array_map(fn(ResolvedItem $item) => $item->productId, $items)));
        if (empty($productIds)) {
            return [];
        }

        $availableIds = Catalog::query()
            ->where('country_code', $country)
            ->where('is_available', 1)
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();

        if (empty($availableIds)) {
            return [];
        }

        if (count($availableIds) === count($productIds)) {
            return $items;
        }

        $allowed = array_fill_keys($availableIds, true);

        return array_values(array_filter($items, fn(ResolvedItem $item) => isset($allowed[$item->productId])));
    }
}
