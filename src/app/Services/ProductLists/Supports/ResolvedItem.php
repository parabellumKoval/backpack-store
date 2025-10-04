<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

class ResolvedItem
{
    public function __construct(
        public readonly int $productId,
        public readonly array $meta = []
    ) {
    }
}
