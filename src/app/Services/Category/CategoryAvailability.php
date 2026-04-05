<?php

namespace Backpack\Store\app\Services\Category;

use Backpack\Store\app\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CategoryAvailability
{
    /**
     * Ensure that the given category is accessible for the requested country.
     *
     * @param  Category|null  $category
     * @param  string|null  $country
     * @param  bool  $fallbackToStore
     * @param  bool  $abortWhenMissing
     * @return Category|null
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function ensure(
        ?Category $category,
        ?string $country = null,
        bool $fallbackToStore = false,
        bool $abortWhenMissing = false
    ): ?Category {
        if (!$category) {
            if ($abortWhenMissing) {
                throw static::notFoundException();
            }

            return null;
        }

        if (!$category->isAvailableForCountry($country, $fallbackToStore)) {
            throw static::notFoundException();
        }

        if (!$category->isAvailableForStorefront(null, $fallbackToStore)) {
            throw static::notFoundException();
        }

        return $category;
    }

    /**
     * Resolve an active category by slug for the provided country.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findActiveBySlugOrFail(
        string $slug,
        ?string $country = null,
        bool $fallbackToStore = false
    ): Category {
        $category = Category::query()
            ->active()
            ->where('slug', $slug)
            ->first();

        return static::ensure($category, $country, $fallbackToStore, true);
    }

    /**
     * Resolve a category by id for the provided country.
     *
     * @throws \Illuminate\Database\Eloquent.ModelNotFoundException
     */
    public static function findByIdOrFail(
        int $id,
        ?string $country = null,
        bool $fallbackToStore = false,
        bool $onlyActive = true
    ): Category {
        $query = Category::query()->where('id', $id);

        if ($onlyActive) {
            $query->active();
        }

        $category = $query->first();

        return static::ensure($category, $country, $fallbackToStore, true);
    }

    protected static function notFoundException(): ModelNotFoundException
    {
        return (new ModelNotFoundException())->setModel(Category::class);
    }
}
