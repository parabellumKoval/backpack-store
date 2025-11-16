<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\AvailabilityGate;
use Backpack\Store\app\Services\ProductLists\Supports\NormalizesAnchors;
use Backpack\Store\app\Services\ProductLists\Supports\ResolvedItem;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;
use Illuminate\Support\Facades\DB;

class CategoryResolver implements SourceResolver
{
    use NormalizesAnchors;

    public function __construct(protected FilterEngine $filterEngine, protected AvailabilityGate $availabilityGate)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'category';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $anchorBaseMap = $this->resolveBaseProductMap($anchors->ids);
        $anchorBaseIds = $this->mapIdsToBase($anchors->ids, $anchorBaseMap);

        $includeChildren = (bool) $definition->param('include_children', false);
        $minShared = $definition->param('min_shared');
        $minShared = is_numeric($minShared) ? max(1, (int) $minShared) : 1;
        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;

        $union = [];
        $seen = [];
        foreach ($anchors->ids as $anchorId) {
            $baseAnchorId = $this->baseIdFor($anchorId, $anchorBaseMap);
            $rows = $this->fetchByCategory(
                $baseAnchorId,
                $anchorId,
                $minShared,
                $perAnchorLimit,
                $includeChildren,
                $anchors->model,
                $context,
                $anchorBaseIds
            );
            foreach ($rows as $item) {
                if (isset($seen[$item->productId])) {
                    continue;
                }
                $union[] = $item;
                $seen[$item->productId] = true;
            }
        }

        if (!empty($definition->filters)) {
            $union = $this->filterEngine->apply($union, $definition->filters, $context);
        }

        return new SourceResult($definition, $union);
    }

    protected function fetchByCategory(
        int $anchorBaseId,
        int $displayAnchorId,
        int $minShared,
        ?int $limit,
        bool $includeChildren,
        string $model,
        ListRequestContext $context,
        array $excludedBaseIds
    ): array
    {
        if ($model !== \Backpack\Store\app\Models\Product::class) {
            return [];
        }

        $categories = $this->anchorCategoryIds($anchorBaseId, $includeChildren, $context->country);
        if (empty($categories)) {
            return [];
        }

        // $query = DB::table('ak_category_product')
        //     ->whereIn('category_id', $categories)
        //     ->where('product_id', '!=', $anchorId)
        //     ->select('product_id', DB::raw('COUNT(*) as matches'))
        //     ->groupBy('product_id')
        //     ->having('matches', '>=', $minShared)
        //     ->orderByDesc('matches');

        $query = DB::table('ak_catalog as c')
            ->join('ak_products as p', 'p.id', '=', 'c.product_id')
            ->select('c.product_id', DB::raw('COUNT(*) as matches'))
            ->where(function ($q) use ($categories) {
                foreach ($categories as $id) {
                    $q->orWhereJsonContains('c.category_ids', (int)$id);
                }
            })
            ->groupBy('c.product_id')
            ->having('matches', '>=', $minShared)
            ->orderByDesc('matches');

        if (!empty($excludedBaseIds)) {
            $query->whereNotIn(DB::raw('COALESCE(p.parent_id, p.id)'), $excludedBaseIds);
        }

        $this->availabilityGate->applyQueryFilter($query, $context->country);

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function ($row) use ($displayAnchorId) {
            return new ResolvedItem((int) $row->product_id, [
                'source' => 'category',
                'anchor_id' => $displayAnchorId,
                'shared_count' => (int) $row->matches,
            ]);
        })->all();
    }

    protected function anchorCategoryIds(int $anchorId, bool $includeChildren, ?string $country = null): array
    {
        $ids = DB::table('ak_category_product')
            ->where('product_id', $anchorId)
            ->pluck('category_id')
            ->toArray();

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!empty($ids) && $country) {
            $ids = \Backpack\Store\app\Models\Category::query()
                ->forCountry($country, true)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                })
                ->toArray();
        }

        if (!$includeChildren || empty($ids)) {
            return $ids;
        }


        $rows = DB::table('ak_product_categories')
            ->select('id', 'parent_id')
            ->whereIn('id', $ids)
            ->get();

        $extra = [];
        foreach ($rows as $row) {
            // $extra = array_merge($extra, DB::table('ak_product_categories')
            //     ->where('parent_id', $row->id)
            //     ->pluck('id')->toArray());

            // DRESS
            // Сделать данные прокаленными, например в запись Category добавить сразу все дерево потомков (ids)
            // очень важно для производительности
            $extra = array_merge($extra, \Backpack\Store\app\Models\Category::getCategoryNodeIdList(null,$row->id, $country));
        }

        return array_values(array_unique(array_merge($ids, $extra)));
    }
}
