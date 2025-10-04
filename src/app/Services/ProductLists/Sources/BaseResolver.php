<?php

namespace Backpack\Store\app\Services\ProductLists\Sources;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\Contracts\SourceResolver;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;

class BaseResolver implements SourceResolver
{
    public function __construct(protected FilterEngine $filterEngine)
    {
    }

    public function supports(string $alias): bool
    {
        return $alias === 'base';
    }

    public function resolve(SourceDefinition $definition, ProductList $list, ListRequestContext $context): SourceResult
    {
        $limit = $definition->param('limit') ?? $definition->param('capacity');
        if (!is_numeric($limit) || (int)$limit <= 0) {
            $limit = max($list->capacity ?? 12, $context->perPage ?? 0, 24);
        }

        $limit = min((int) $limit, 200);

        $items = $this->filterEngine->collect($definition->filters, $context, $limit);

        return new SourceResult($definition, $items);
    }
}
