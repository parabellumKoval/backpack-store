<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Http\Resources\AttributeProductResource;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class ProductLargeResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      // $mods = $this->modifications ?? [];
      $mods = $this->resource_modifications ?? [];

      return [
        'id' => $this->product_id ?? $this->id,
        'group_id' => $this->group_id,
        'code' => $this->code,
        'name' => $this->name,
        'short_name' => $this->short_name,
        'inStock' => $this->in_stock,
        'slug' => $this->slug,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'sale' => $this->sale,
        'rating' => $this->rating,
        'reviews' => $this->reviews,
        'ratings' => $this->ratings,
        // 'reviews_rating_detailes' => $this->reviewsRatingDetailes,
        'images' => $this->getImageSourcesForApi(),
        'content' => $this->content,
        'categories' => $this->resolveCategories(),
        'brand' => $this->formatBrand(),
        // 'categories' => $this->categories && $this->categories->count()? 
        //   self::$resources['category']['tiny']::collection($this->categories): 
        //     null,
        'attrs' => $this->properties,
        'custom_attrs' => $this->customProperties,
        'modifications' => $mods,
        'seo' => $this->seoArray
      ];
    }

    /**
     * Prepare categories with full parent chain for API consumers.
     *
     * @return array
     */
    protected function resolveCategories(): array
    {
      $categories = $this->fetchCategories();

      if ($categories->isEmpty()) {
        return [];
      }

      return $categories
        ->map(function ($category) {
          return $this->formatCategoryBranch($category);
        })
        ->filter()
        ->values()
        ->toArray();
    }

    /**
     * Convert a single category into array with nested parents.
     *
     * @param  \Backpack\Store\app\Models\Category|null  $category
     * @return array|null
     */
    protected function formatCategoryBranch($category): ?array
    {
      if (!$category) {
        return null;
      }

      if (method_exists($category, 'getParentNode')) {
        $nodes = $category->getParentNode($category);

        if ($nodes && $nodes->count()) {
          $branch = null;

          foreach ($nodes->reverse() as $node) {
            $branch = [
              'id' => $node->id,
              'name' => $node->name,
              'slug' => $node->slug,
              'parent' => $branch,
            ];
          }

          return $branch;
        }
      }

      return [
        'id' => $category->id,
        'name' => $category->name,
        'slug' => $category->slug,
        'parent' => null,
      ];
    }

    /**
     * Format brand info for the resource.
     *
     * @return array|null
     */
    protected function formatBrand(): ?array
    {
      $brand = $this->resource->brand ?? null;

      if (!$brand) {
        return null;
      }

      return [
        'id' => $brand->id,
        'name' => $brand->name,
        'slug' => $brand->slug,
        'images' => method_exists($brand, 'getImageSourcesForApi')
          ? $brand->getImageSourcesForApi()
          : [],
      ];
    }

    /**
     * Get categories collection regardless of resource implementation.
     */
    protected function fetchCategories(): Collection
    {
      $resource = $this->resource;

      if (!method_exists($resource, 'categories')) {
        return collect();
      }

      $categoriesResult = $resource->categories();

      if ($categoriesResult instanceof Relation) {
        if ($resource->relationLoaded('categories')) {
          return $resource->getRelation('categories');
        }

        $loaded = $categoriesResult->getResults();
        return $loaded instanceof Collection ? $loaded : collect($loaded);
      }

      if ($categoriesResult instanceof Collection) {
        return $categoriesResult;
      }

      if (is_array($categoriesResult)) {
        return collect($categoriesResult);
      }

      return collect();
    }
}
