<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\CategoryChanged;
use Backpack\Store\app\Job\TouchCategoryCatalogProductsJob;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Services\Catalog\CategoryCatalogTouchService;
use Backpack\Store\Facades\Store;

class CategoryObserver
{
    /**
     * Only changes that alter product availability/category ancestry in ak_catalog
     * should trigger touching linked products.
     */
    protected const CATALOG_RELEVANT_FIELDS = [
        'countries',
        'store_only_countries',
        'parent_id',
    ];

    public function saved(Category $category): void
    {
        event(CategoryChanged::for($category, 'saved'));

        if ($this->shouldTouchCatalog($category)) {
            $this->queueCatalogTouch($category);
        }
    }

    public function deleted(Category $category): void
    {
        event(CategoryChanged::for($category, 'deleted'));
    }

    protected function queueCatalogTouch(Category $category): void
    {
        if ($this->shouldTouchCatalogInline()) {
            app(CategoryCatalogTouchService::class)->touchByCategoryId((int) $category->id, true);
            return;
        }

        TouchCategoryCatalogProductsJob::dispatch((int) $category->id);
    }

    protected function shouldTouchCatalog(Category $category): bool
    {
        if (!Store::isCacheTable()) {
            return false;
        }

        foreach (self::CATALOG_RELEVANT_FIELDS as $field) {
            if ($category->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldTouchCatalogInline(): bool
    {
        if (config('dress.store.catalog.touch_inline', false)) {
            return true;
        }

        if (config('queue.default') === 'sync') {
            return true;
        }

        return false;
    }
}
