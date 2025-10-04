<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;
use Illuminate\Support\Facades\DB;

class LinksResolver implements SourceResolver
{
    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'links';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $kind = $this->normalizeKind($definition->param('kind'));
        $minPriority = $definition->param('min_priority');
        $minPriority = is_numeric($minPriority) ? (int) $minPriority : null;
        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;
        $includeReverse = (bool) $definition->param('include_reverse', false);

        $items = [];
        $seen = [];

        foreach ($anchors->ids as $anchorId) {
            $forward = $this->fetchForwardLinks($anchors->model, $anchorId, $kind, $minPriority, $perAnchorLimit);
            foreach ($forward as $row) {
                if ($anchorId === (int) $row->product_id) {
                    continue;
                }
                if (isset($seen[$row->product_id])) {
                    continue;
                }
                $items[] = new ResolvedItem((int) $row->product_id, [
                    'source' => 'links',
                    'anchor_id' => $anchorId,
                    'priority' => (int) $row->priority,
                    'kind' => $row->kind,
                ]);
                $seen[$row->product_id] = true;
            }

            if ($includeReverse) {
                $reverseRows = $this->fetchReverseLinks($anchors->model, $anchorId, $kind, $minPriority, $perAnchorLimit);
                foreach ($reverseRows as $row) {
                    $productId = (int) $row->linkable_id;
                    if ($productId === $anchorId) {
                        continue;
                    }
                    if (isset($seen[$productId])) {
                        continue;
                    }
                    $items[] = new ResolvedItem($productId, [
                        'source' => 'links',
                        'anchor_id' => $anchorId,
                        'priority' => (int) $row->priority,
                        'kind' => $row->kind,
                        'reverse' => true,
                    ]);
                    $seen[$productId] = true;
                }
            }
        }

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        return new SourceResult($definition, $items);
    }

    protected function fetchForwardLinks(string $model, int $anchorId, ?string $kind, ?int $minPriority, ?int $limit)
    {
        $query = DB::table('ak_product_links')
            ->where('linkable_type', $model)
            ->where('linkable_id', $anchorId)
            ->orderByDesc('priority')
            ->orderBy('lft');

        if ($kind && $kind !== 'any') {
            $query->where('kind', $kind);
        }

        if ($minPriority !== null) {
            $query->where('priority', '>=', $minPriority);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    protected function fetchReverseLinks(string $model, int $anchorId, ?string $kind, ?int $minPriority, ?int $limit)
    {
        $query = DB::table('ak_product_links')
            ->where('product_id', $anchorId)
            ->where('linkable_type', $model)
            ->orderByDesc('priority')
            ->orderBy('lft');

        if ($kind && $kind !== 'any') {
            $query->where('kind', $kind);
        }

        if ($minPriority !== null) {
            $query->where('priority', '>=', $minPriority);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    protected function normalizeKind(mixed $kind): ?string
    {
        if (!is_string($kind) || $kind === '' || $kind === 'any') {
            return null;
        }
        $kind = strtolower($kind);
        return in_array($kind, ['up', 'cross'], true) ? $kind : null;
    }
}
