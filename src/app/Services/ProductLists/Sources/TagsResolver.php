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

class TagsResolver implements SourceResolver
{
    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'tags';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $matchMode = strtoupper((string) $definition->param('match_mode', 'ANY')) === 'ALL' ? 'ALL' : 'ANY';
        $minShared = $definition->param('min_shared');
        $minShared = is_numeric($minShared) ? max(1, (int) $minShared) : 1;
        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;

        $perAnchor = [];
        foreach ($anchors->ids as $anchorId) {
            $perAnchor[$anchorId] = $this->fetchByTags($anchorId, $minShared, $perAnchorLimit, $anchors->model);
        }

        $items = $this->combinePerAnchor($perAnchor, $matchMode, $anchors->ids);

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        return new SourceResult($definition, $items);
    }

    protected function fetchByTags(int $anchorId, int $minShared, ?int $limit, string $model): array
    {
        // пока поддерживаем только продуктовые якоря
        if ($model !== \Backpack\Store\app\Models\Product::class) {
            return [];
        }

        $query = DB::table('ak_product_tag as t1')
            ->join('ak_product_tag as t2', 't1.tag_id', '=', 't2.tag_id')
            ->where('t1.product_id', $anchorId)
            ->where('t2.product_id', '!=', $anchorId)
            ->select('t2.product_id', DB::raw('COUNT(*) as matches'))
            ->groupBy('t2.product_id')
            ->having('matches', '>=', $minShared)
            ->orderByDesc('matches');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row) use ($anchorId) {
            return new ResolvedItem((int) $row->product_id, [
                'source' => 'tags',
                'anchor_id' => $anchorId,
                'shared_count' => (int) $row->matches,
            ]);
        })->all();
    }

    /**
     * @param  array<int, ResolvedItem[]> $perAnchor
     * @param  string $matchMode
     * @param  int[] $anchorIds
     * @return ResolvedItem[]
     */
    protected function combinePerAnchor(array $perAnchor, string $matchMode, array $anchorIds): array
    {
        if ($matchMode === 'ALL' && count($anchorIds) > 1) {
            $intersection = null;
            foreach ($anchorIds as $anchorId) {
                $items = $perAnchor[$anchorId] ?? [];
                $ids = array_map(fn($item) => $item->productId, $items);
                if ($intersection === null) {
                    $intersection = $ids;
                } else {
                    $intersection = array_values(array_intersect($intersection, $ids));
                }
            }
            if (empty($intersection)) {
                return [];
            }

            $scoreMap = [];
            foreach ($perAnchor as $items) {
                foreach ($items as $item) {
                    if (!in_array($item->productId, $intersection, true)) {
                        continue;
                    }
                    $scoreMap[$item->productId] = ($scoreMap[$item->productId] ?? 0) + ($item->meta['shared_count'] ?? 0);
                }
            }

            $result = [];
            foreach ($intersection as $productId) {
                $result[] = new ResolvedItem($productId, [
                    'source' => 'tags',
                    'shared_count' => $scoreMap[$productId] ?? 0,
                ]);
            }

            usort($result, fn($a, $b) => ($b->meta['shared_count'] ?? 0) <=> ($a->meta['shared_count'] ?? 0));
            return $result;
        }

        $union = [];
        $seen = [];
        foreach ($anchorIds as $anchorId) {
            foreach ($perAnchor[$anchorId] ?? [] as $item) {
                if (isset($seen[$item->productId])) {
                    continue;
                }
                $union[] = $item;
                $seen[$item->productId] = true;
            }
        }
        return $union;
    }
}
