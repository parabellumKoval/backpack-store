<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\NormalizesAnchors;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;
use Illuminate\Support\Facades\DB;

class BoughtTogetherResolver implements SourceResolver
{
    use NormalizesAnchors;

    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'bought_together';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $minScore = $definition->param('min_score');
        $minScore = is_numeric($minScore) ? (int) $minScore : null;
        $fallbackGlobal = (bool) $definition->param('fallback_global', false);
        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;

        $anchorBaseMap = $this->resolveBaseProductMap($anchors->ids);
        $anchorBaseIds = $this->mapIdsToBase($anchors->ids, $anchorBaseMap);
        $anchorBaseLookup = $this->baseLookup($anchorBaseIds);

        $items = [];
        $seen = [];
        foreach ($anchors->ids as $anchorId) {
            $baseAnchorId = $anchorBaseMap[$anchorId] ?? $anchorId;
            $chunk = $this->fetchBoughtTogether(
                $baseAnchorId,
                $context->country,
                $minScore,
                $perAnchorLimit,
                $anchorBaseIds
            );
            $this->pushRows($items, $seen, $chunk, $anchorId, false, $anchorBaseLookup);

            if ($fallbackGlobal && ($perAnchorLimit === null || count($chunk) < $perAnchorLimit)) {
                $globalLimit = $perAnchorLimit ? max(0, $perAnchorLimit - count($chunk)) : null;
                $globalRows = $this->fetchBoughtTogether(
                    $baseAnchorId,
                    null,
                    $minScore,
                    $globalLimit,
                    $anchorBaseIds
                );
                $this->pushRows($items, $seen, $globalRows, $anchorId, true, $anchorBaseLookup);
            }
        }

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        return new SourceResult($definition, $items);
    }

    protected function fetchBoughtTogether(
        int $anchorBaseId,
        ?string $country,
        ?int $minScore,
        ?int $limit,
        array $excludedBaseIds
    ) {
        $query = DB::table('ak_bought_together as bt')
            ->join('ak_products as anchor_products', 'anchor_products.id', '=', 'bt.product_id')
            ->join('ak_products as with_products', 'with_products.id', '=', 'bt.with_product_id')
            ->select([
                'bt.with_product_id',
                'bt.score',
                DB::raw('COALESCE(with_products.parent_id, with_products.id) as with_base_product_id'),
            ])
            ->whereRaw('COALESCE(anchor_products.parent_id, anchor_products.id) = ?', [$anchorBaseId])
            ->orderByDesc('bt.score');

        if ($country === null) {
            $query->whereNull('bt.country_code');
        } else {
            $query->where('bt.country_code', $country);
        }

        if ($minScore !== null) {
            $query->where('bt.score', '>=', $minScore);
        }

        if (!empty($excludedBaseIds)) {
            $query->whereNotIn(
                DB::raw('COALESCE(with_products.parent_id, with_products.id)'),
                $excludedBaseIds
            );
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    protected function pushRows(
        array &$items,
        array &$seen,
        $rows,
        int $anchorId,
        bool $global,
        array $anchorBaseLookup
    ): void {
        foreach ($rows as $row) {
            $productId = (int) $row->with_product_id;
            $withBaseId = isset($row->with_base_product_id)
                ? (int) $row->with_base_product_id
                : $productId;

            if (isset($anchorBaseLookup[$withBaseId])) {
                continue;
            }

            if ($productId === $anchorId) {
                continue;
            }

            if (isset($seen[$productId])) {
                continue;
            }
            $items[] = new ResolvedItem($productId, [
                'source' => 'bought_together',
                'anchor_id' => $anchorId,
                'score' => (int) $row->score,
                'fallback_global' => $global,
            ]);
            $seen[$productId] = true;
        }
    }

}
