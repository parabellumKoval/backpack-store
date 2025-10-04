<?php

namespace Backpack\Store\app\Services\ProductLists\Supports;

class AnchorSelection
{
    /**
     * @param  string $alias API alias (e.g. "product")
     * @param  string $model Fully qualified model class name
     * @param  int[]  $ids   Resolved anchor identifiers
     */
    public readonly array $ids;

    public function __construct(
        public readonly string $alias,
        public readonly string $model,
        array $ids
    ) {
        $this->ids = array_values(array_unique(array_map('intval', $ids)));
    }

    public function isEmpty(): bool
    {
        return empty($this->ids);
    }
}
