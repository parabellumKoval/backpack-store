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

class BrandResolver implements SourceResolver
{
    use NormalizesAnchors;

    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'brand';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $anchorBaseMap = $this->resolveBaseProductMap($anchors->ids);
        $anchorBaseIds = $this->mapIdsToBase($anchors->ids, $anchorBaseMap);

        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;

        $items = [];
        $seen = [];
        foreach ($anchors->ids as $anchorId) {
            $baseAnchorId = $this->baseIdFor($anchorId, $anchorBaseMap);
            $brandId = $this->anchorBrandId($baseAnchorId);
            if ($brandId === null) {
                continue;
            }
            $rows = $this->fetchBrandProducts(
                $brandId,
                $context->country,
                $context->storefront,
                $perAnchorLimit,
                $anchorBaseIds
            );
            foreach ($rows as $row) {
                $productId = (int) $row->product_id;
                if (isset($seen[$productId])) {
                    continue;
                }
                $items[] = new ResolvedItem($productId, [
                    'source' => 'brand',
                    'anchor_id' => $anchorId,
                    'brand_id' => $brandId,
                ]);
                $seen[$productId] = true;
            }
        }

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        return new SourceResult($definition, $items);
    }

    protected function anchorBrandId(int $productId): ?int
    {
        $brandId = DB::table('ak_products')->where('id', $productId)->value('brand_id');
        return $brandId ? (int) $brandId : null;
    }

    protected function fetchBrandProducts(int $brandId, string $country, string $storefront, ?int $limit, array $excludedBaseIds)
    {
        $query = DB::table('ak_catalog as c')
            ->join('ak_products as p', 'p.id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.storefront_code', $storefront)
            ->where('c.brand_id', $brandId)
            ->orderByDesc('c.product_id');

        if (!empty($excludedBaseIds)) {
            $query->whereNotIn(DB::raw('COALESCE(p.parent_id, p.id)'), $excludedBaseIds);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->select('c.product_id')->get();
    }
}
