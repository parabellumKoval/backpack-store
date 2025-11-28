<?php

namespace Backpack\Store\app\Events;

use Backpack\Store\app\Models\ProductList;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductListChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $listId,
        public ?string $slug,
        public string $action
    ) {
    }

    public static function for(ProductList $list, string $action): self
    {
        return new self($list->id, $list->slug, $action);
    }
}
