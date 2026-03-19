<?php

namespace Backpack\Store\app\Services\Catalog;

use Backpack\Store\app\Job\SyncCatalogProductJob;
use Backpack\Store\app\Models\Category;
use Backpack\Store\Facades\Store;
use Illuminate\Support\Facades\DB;

class CategoryCatalogTouchService
{
    public function touchByCategoryId(int $categoryId, bool $forceImmediate = false): void
    {
        if ($categoryId <= 0 || !Store::isCacheTable()) {
            return;
        }

        $category = Category::query()->find($categoryId);

        if (!$category) {
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
            ->chunkById(200, function ($rows) use ($forceImmediate) {
                $productIds = collect($rows)
                    ->pluck('product_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($productIds->isEmpty()) {
                    return true;
                }

                $childIds = DB::table('ak_products')
                    ->whereIn('parent_id', $productIds->all())
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $allIds = $productIds->merge($childIds)->unique();

                /** @var CatalogCacheService|null $catalogSync */
                $catalogSync = $forceImmediate ? app(CatalogCacheService::class) : null;

                foreach ($allIds as $productId) {
                    $productId = (int) $productId;

                    if ($productId <= 0) {
                        continue;
                    }

                    if ($forceImmediate && $catalogSync) {
                        $catalogSync->syncProduct($productId);
                        continue;
                    }

                    SyncCatalogProductJob::dispatch($productId);
                }

                return true;
            });
    }

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
}
