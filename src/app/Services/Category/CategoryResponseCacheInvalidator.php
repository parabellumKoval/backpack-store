<?php

namespace Backpack\Store\app\Services\Category;

use Backpack\Store\app\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryResponseCacheInvalidator
{
    public function invalidate(Category $category): void
    {
        $slugs = $this->collectAffectedSlugs($category);

        if (empty($slugs)) {
            return;
        }

        foreach ($slugs as $slug) {
            foreach ($this->cacheKeysForSlug($slug) as $cacheKey) {
                Cache::forget($cacheKey);
            }
        }
    }

    protected function collectAffectedSlugs(Category $category): array
    {
        $slugs = [];

        $this->pushSlug($slugs, $category->slug ?? null);
        $this->pushSlug($slugs, $category->getOriginal('slug'));

        foreach ($this->ancestorSlugsForCategory($category) as $slug) {
            $this->pushSlug($slugs, $slug);
        }

        foreach ($this->originalAncestorSlugs($category) as $slug) {
            $this->pushSlug($slugs, $slug);
        }

        foreach ($this->subtreeSlugsForCategory($category) as $slug) {
            $this->pushSlug($slugs, $slug);
        }

        return array_values(array_unique($slugs));
    }

    protected function pushSlug(array &$slugs, ?string $slug): void
    {
        $slug = is_string($slug) ? trim($slug) : '';

        if ($slug !== '') {
            $slugs[] = $slug;
        }
    }

    protected function ancestorSlugsForCategory(Category $category): array
    {
        return $this->ancestorSlugsFromParentId($category->parent_id);
    }

    protected function originalAncestorSlugs(Category $category): array
    {
        $originalParentId = $category->getOriginal('parent_id');

        if (!$originalParentId || (int) $originalParentId === (int) $category->parent_id) {
            return [];
        }

        return $this->ancestorSlugsFromParentId((int) $originalParentId);
    }

    protected function ancestorSlugsFromParentId(?int $parentId): array
    {
        if (!$parentId) {
            return [];
        }

        $parent = Category::query()->find($parentId);

        if (!$parent) {
            return [];
        }

        return $parent->getParentNode($parent)
            ->pluck('slug')
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->values()
            ->all();
    }

    protected function subtreeSlugsForCategory(Category $category): array
    {
        $query = Category::query()->select(['id', 'slug', 'parent_id', 'lft', 'rgt']);

        if ($this->hasTreeBounds($category)) {
            return $query
                ->where('lft', '>=', $category->lft)
                ->where('rgt', '<=', $category->rgt)
                ->pluck('slug')
                ->filter(fn ($slug) => is_string($slug) && $slug !== '')
                ->values()
                ->all();
        }

        return $this->subtreeSlugsFallback((int) $category->id);
    }

    protected function subtreeSlugsFallback(int $rootId): array
    {
        if ($rootId <= 0) {
            return [];
        }

        $visited = [];
        $queue = [$rootId];
        $slugs = [];

        while (!empty($queue)) {
            $currentId = (int) array_shift($queue);

            if ($currentId <= 0 || isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;

            $category = Category::query()
                ->select(['id', 'slug'])
                ->find($currentId);

            if ($category && is_string($category->slug) && $category->slug !== '') {
                $slugs[] = $category->slug;
            }

            $children = Category::query()
                ->where('parent_id', $currentId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            foreach ($children as $childId) {
                if ($childId > 0 && !isset($visited[$childId])) {
                    $queue[] = $childId;
                }
            }
        }

        return $slugs;
    }

    protected function hasTreeBounds(Category $category): bool
    {
        return $category->lft !== null
            && $category->rgt !== null
            && $category->lft <= $category->rgt;
    }

    protected function cacheKeysForSlug(string $slug): array
    {
        $keys = [];

        foreach ($this->countries() as $country) {
            foreach ($this->storefronts() as $storefront) {
                foreach ($this->locales() as $locale) {
                    $keys[] = $this->cacheKey('category-data', $slug, $country, $storefront, $locale);
                }
            }
        }

        return array_values(array_unique($keys));
    }

    protected function cacheKey(
        string $prefix,
        string $slug,
        ?string $country,
        ?string $storefront,
        ?string $locale
    ): string {
        return implode(':', [
            $prefix,
            trim($slug),
            strtolower(trim((string) ($country ?? ''))),
            strtolower(trim((string) ($storefront ?? ''))),
            strtolower(trim((string) ($locale ?? ''))),
        ]);
    }

    protected function countries(): array
    {
        $countries = array_keys(\Store::countries() ?? []);
        $default = \Settings::get('dress.multistore.default_country');
        $global = \Store::globalRegion();

        return $this->normalizeDimension(array_merge($countries, [$default, $global, '']));
    }

    protected function storefronts(): array
    {
        $storefronts = array_keys(\Store::storefronts() ?? []);
        $default = \Store::defaultStorefront();

        return $this->normalizeDimension(array_merge($storefronts, [$default, '']));
    }

    protected function locales(): array
    {
        $crudLocales = array_keys(config('backpack.crud.locales', []));
        $appLocale = config('app.locale');

        return $this->normalizeDimension(array_merge($crudLocales, [$appLocale, '']));
    }

    protected function normalizeDimension(array $values): array
    {
        return array_values(array_unique(array_map(
            static fn ($value) => strtolower(trim((string) ($value ?? ''))),
            $values
        )));
    }
}
