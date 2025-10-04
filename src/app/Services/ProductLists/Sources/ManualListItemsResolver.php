<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductListItem;
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
            $items = $this->itemsFromLegacyTable($list->id ?? 0);
        }

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

    protected function itemsFromLegacyTable(int $listId): array
    {
        if ($listId <= 0) {
            return [];
        }

        return ProductListItem::query()
            ->where('list_id', $listId)
            ->orderByDesc('priority')
            ->get()
            ->map(fn($row) => new ResolvedItem($row->product_id, [
                'is_manual' => true,
                'priority' => (int) $row->priority,
            ]))
            ->all();
    }
}
