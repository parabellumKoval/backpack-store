<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\CategoryChanged;
use Backpack\Store\app\Models\Category;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        event(CategoryChanged::for($category, 'saved'));
    }

    public function deleted(Category $category): void
    {
        event(CategoryChanged::for($category, 'deleted'));
    }
}
