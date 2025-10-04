<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

class SourceResult
{
    /**
     * @param  ResolvedItem[] $items
     */
    public function __construct(
        public readonly SourceDefinition $definition,
        public readonly array $items,
        public readonly array $meta = []
    ) {
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }
}
