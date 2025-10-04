<?php

namespace Backpack\Store\app\Services\ProductLists\Contracts;

use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Services\ProductLists\ListRequestContext;
use Backpack\Store\app\Services\ProductLists\Supports\SourceDefinition;
use Backpack\Store\app\Services\ProductLists\Supports\SourceResult;

interface SourceResolver
{
    public function supports(string $alias): bool;

    public function resolve(
        SourceDefinition $definition,
        ProductList $list,
        ListRequestContext $context
    ): SourceResult;
}
