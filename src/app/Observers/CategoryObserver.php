<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\CategoryChanged;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;
use Backpack\Store\Facades\Store;
use Illuminate\Support\Facades\DB;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        event(CategoryChanged::for($category, 'saved'));

        if ($this->shouldTouchCatalog($category)) {
            $this->touchCatalogProducts($category);
        }
    }

    public function deleted(Category $category): void
    {
        event(CategoryChanged::for($category, 'deleted'));
    }

    protected function touchCatalogProducts(Category $category): void
    {
        if (!Store::isCacheTable()) {
            return;
        }

        $categoryIds = $this->collectSubtreeCategoryIds($category);

        if (empty($categoryIds)) {
            return;
        }

        DB::table('ak_category_product')
            ->select('id', 'product_id')
            ->whereIn('category_id', $categoryIds)
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

    /**
     * Собрать идентификаторы текущей категории и всех потомков.
     */
    protected function collectSubtreeCategoryIds(Category $category): array
    {
        if ($this->hasTreeBounds($category)) {
            $ids = Category::query()
                ->where('lft', '>=', $category->lft)
                ->where('rgt', '<=', $category->rgt)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (!empty($ids)) {
                return $ids;
            }
        }

        return $this->collectSubtreeIdsFallback((int) $category->id);
    }

    protected function collectSubtreeIdsFallback(int $rootId): array
    {
        $ids = [$rootId];
        $currentLevel = [$rootId];

        while (!empty($currentLevel)) {
            $children = Category::query()
                ->whereIn('parent_id', $currentLevel)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $children = array_values(array_diff($children, $ids));

            if (empty($children)) {
                break;
            }

            $ids = array_merge($ids, $children);
            $currentLevel = $children;
        }

        return $ids;
    }

    protected function hasTreeBounds(Category $category): bool
    {
        return $category->lft !== null
            && $category->rgt !== null
            && $category->lft <= $category->rgt;
    }

    protected function shouldTouchCatalog(Category $category): bool
    {
        if (!Store::isCacheTable()) {
            return false;
        }

        // Категории влияют на активацию/видимость/FAQ и структуру каталога;
        // пересобираем привязанные товары на каждое сохранение категории.
        return true;
    }
}
