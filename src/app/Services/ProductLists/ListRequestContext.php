<?php

namespace Backpack\Store\app\Services\ProductLists;

use Backpack\Store\app\Services\ProductLists\Supports\AnchorSelection;

class ListRequestContext
{
    public function __construct(
        public readonly string $page,
        public readonly string $country,
        public readonly string $lang,
        public readonly ?AnchorSelection $anchors = null,
        public readonly ?int $capacityOverride = null,
        public readonly ?int $pageNumber = null,
        public readonly ?int $perPage = null
    ) {
    }

    public function withOverrides(?int $capacityOverride = null, ?int $pageNumber = null, ?int $perPage = null): self
    {
        return new self(
            $this->page,
            $this->country,
            $this->lang,
            $this->anchors,
            $capacityOverride ?? $this->capacityOverride,
            $pageNumber ?? $this->pageNumber,
            $perPage ?? $this->perPage
        );
    }
}
