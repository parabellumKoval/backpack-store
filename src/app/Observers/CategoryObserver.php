<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\CategoryChanged;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;
use Illuminate\Support\Facades\DB;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        event(CategoryChanged::for($category, 'saved'));

        if ($category->wasChanged('countries')) {
            $this->touchCatalogProducts($category);
        }
    }

    public function deleted(Category $category): void
    {
        event(CategoryChanged::for($category, 'deleted'));
    }

    protected function touchCatalogProducts(Category $category): void
    {
        if (!(bool) \Settings::get('dress.store.catalog_table_cache', false)) {
            return;
        }

        DB::table('ak_category_product')
            ->select('id', 'product_id')
            ->where('category_id', $category->id)
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $productIds = collect($rows)
                    ->pluck('product_id')
                    ->filter()
                    ->unique()
                    ->values();

                if ($productIds->isEmpty()) {
                    return true;
                }

                $childIds = DB::table('ak_products')
                    ->whereIn('parent_id', $productIds->all())
                    ->pluck('id')
                    ->all();

                $allIds = $productIds->merge($childIds)->unique();

                foreach ($allIds as $productId) {
                    CatalogSyncTouch::touch((int) $productId);
                }

                return true;
            });
    }
}
