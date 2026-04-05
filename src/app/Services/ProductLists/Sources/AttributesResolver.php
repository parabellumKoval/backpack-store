<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\NormalizesAnchors;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;
use Illuminate\Support\Facades\DB;

class AttributesResolver implements SourceResolver
{
    use NormalizesAnchors;

    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'attributes';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $anchors = $context->anchors;
        if ($anchors === null || $anchors->isEmpty()) {
            return new SourceResult($definition, []);
        }

        $anchorBaseMap = $this->resolveBaseProductMap($anchors->ids);
        $anchorBaseIds = $this->mapIdsToBase($anchors->ids, $anchorBaseMap);
        $anchorBaseLookup = $this->baseLookup($anchorBaseIds);

        $logic = $definition->param('logic') ?? 'AND';
        $rules = $definition->param('rules', []);
        if (!is_array($rules) || empty($rules)) {
            return new SourceResult($definition, []);
        }

        $preparedRules = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $attributeId = $rule['attribute'] ?? null;
            if (!is_numeric($attributeId)) {
                continue;
            }
            $values = $rule['values'] ?? null;
            if (empty($values)) {
                $values = $this->anchorAttributeValues($anchorBaseIds, (int) $attributeId, $context);
            }
            if (empty($values)) {
                continue;
            }
            $rule['values'] = $values;
            $preparedRules[] = $rule;
        }

        if (empty($preparedRules)) {
            return new SourceResult($definition, []);
        }

        $filter = [[
            'type' => 'attributes',
            'logic' => $logic,
            'rules' => $preparedRules,
            'direction' => 'include',
        ]];

        $perAnchorLimit = $definition->param('per_anchor_limit');
        $perAnchorLimit = is_numeric($perAnchorLimit) ? (int) $perAnchorLimit : null;
        $limit = $perAnchorLimit ? $perAnchorLimit * max(count($anchorBaseIds), 1) : 100;

        $items = $this->filterEngine->collect($filter, $context, $limit);

        if (!empty($definition->filters)) {
            $items = $this->filterEngine->apply($items, $definition->filters, $context);
        }

        $items = $this->removeAnchorsFromItems($items, $anchorBaseLookup);

        return new SourceResult($definition, $items);
    }

    protected function anchorAttributeValues(array $anchorBaseIds, int $attributeId, ListRequestContext $context): array
    {
        if (empty($anchorBaseIds)) {
            return [];
        }

        $rows = DB::table('ak_catalog as c')
            ->join('ak_products as p', 'p.id', '=', 'c.product_id')
            ->join('ak_catalog_attr as ca', function ($join) use ($attributeId, $context) {
                $join->on('ca.group_id', '=', 'c.group_id')
                     ->where('ca.country_code', '=', $context->country)
                     ->where('ca.storefront_code', '=', $context->storefront)
                     ->where('ca.attribute_id', '=', $attributeId);
            })
            ->where('c.country_code', $context->country)
            ->where('c.storefront_code', $context->storefront)
            ->whereIn(DB::raw('COALESCE(p.parent_id, p.id)'), $anchorBaseIds)
            ->select('ca.attribute_value_id', 'ca.value')
            ->get();

        $values = [];
        foreach ($rows as $row) {
            if ($row->attribute_value_id) {
                $values[] = (int) $row->attribute_value_id;
            } elseif ($row->value !== null) {
                $values[] = (float) $row->value;
            }
        }

        return array_values(array_unique($values));
    }
}
