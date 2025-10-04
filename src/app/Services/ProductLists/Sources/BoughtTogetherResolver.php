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

class BoughtTogetherResolver implements SourceResolver
{
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

        $items = [];
        $seen = [];
        foreach ($anchors->ids as $anchorId) {
            $chunk = $this->fetchBoughtTogether($anchorId, $context->country, $minScore, $perAnchorLimit);
            $this->pushRows($items, $seen, $chunk, $anchorId, false);

            if ($fallbackGlobal && ($perAnchorLimit === null || count($chunk) < $perAnchorLimit)) {
                $globalLimit = $perAnchorLimit ? max(0, $perAnchorLimit - count($chunk)) : null;
                $globalRows = $this->fetchBoughtTogether($anchorId, null, $minScore, $globalLimit);
                $this->pushRows($items, $seen, $globalRows, $anchorId, true);
            }
        }

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        return new SourceResult($definition, $items);
    }

    protected function fetchBoughtTogether(int $anchorId, ?string $country, ?int $minScore, ?int $limit)
    {
        $query = DB::table('ak_bought_together')
            ->where('product_id', $anchorId)
            ->orderByDesc('score');

        if ($country === null) {
            $query->whereNull('country_code');
        } else {
            $query->where('country_code', $country);
        }

        if ($minScore !== null) {
            $query->where('score', '>=', $minScore);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    protected function pushRows(array &$items, array &$seen, $rows, int $anchorId, bool $global): void
    {
        foreach ($rows as $row) {
            $productId = (int) $row->with_product_id;
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
