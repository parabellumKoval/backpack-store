<?php

namespace Backpack\Store\app\Events;

use Backpack\Store\app\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $categoryId,
        public string $slug,
        public string $action
    ) {
    }

    public static function for(Category $category, string $action): self
    {
        return new self($category->id, (string) $category->slug, $action);
    }
}
