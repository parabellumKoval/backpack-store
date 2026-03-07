<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Http\Resources\AttributeProductResource;
use Backpack\Store\app\Services\Campaign\CampaignPayloadService;
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
      $storeOnly = (bool) ($this->store_only ?? false);

      return [
        'id' => $this->product_id ?? $this->id,
        'group_id' => $this->group_id,
        'code' => $this->code,
        'name' => $this->name,
        'short_name' => $this->short_name,
        'inStock' => $this->in_stock,
        'store_only' => $storeOnly,
        'storeOnly' => $storeOnly,
        'slug' => $this->slug,
        'base_modification_slug' => $this->resolveBaseModificationSlug($mods),
        'price' => $this->price,
        'old_price' => $this->old_price,
        'base_price' => $this->base_price ?? ($this->campaign ? $this->old_price : $this->price),
        'oldPrice' => $this->old_price,
        'basePrice' => $this->base_price ?? ($this->campaign ? $this->old_price : $this->price),
        'campaign_discount_amount' => $this->campaign_discount_amount ?? 0,
        'campaignDiscount' => $this->campaign_discount_amount ?? 0,
        'sale' => $this->sale,
        'campaign' => app(CampaignPayloadService::class)->make($this->campaign),
        'rating' => $this->rating,
        'reviews' => $this->reviews,
        'ratings' => $this->ratings,
        // 'reviews_rating_detailes' => $this->reviewsRatingDetailes,
        'images' => $this->getImageSourcesForApi(),
        'content' => $this->resolveRegionalContentField('content'),
        'excerpt' => $this->resolveRegionalContentField('excerpt'),
        'merchant_content' => $this->resolveRegionalContentField('merchant_content'),
        'categories' => $this->resolveCategories(),
        'brand' => $this->formatBrand(),
        // 'categories' => $this->categories && $this->categories->count()? 
        //   self::$resources['category']['tiny']::collection($this->categories): 
        //     null,
        'attrs' => $this->properties,
        'custom_attrs' => $this->customProperties,
        'modifications' => $mods,
        'seo' => $this->resolveSeo(),
        'available_regions' => $this->resolveAvailableRegions(),
      ];
    }

    protected function resolveRegionalContentField(string $attribute)
    {
      $country = \Store::context()->country ?? null;
      $locale = backpack_translatable_request_locale(null) ?? app()->getLocale();

      if (method_exists($this->resource, 'getRegionalContentValue')) {
        return $this->resource->getRegionalContentValue($attribute, $country, $locale);
      }

      $value = $this->{$attribute} ?? null;

      if (is_array($value)) {
        $targetLocale = $locale ?: config('app.fallback_locale');

        if ($targetLocale && isset($value[$targetLocale]) && trim((string) $value[$targetLocale]) !== '') {
          return $value[$targetLocale];
        }

        $fallbackLocale = config('app.fallback_locale');

        if ($fallbackLocale && isset($value[$fallbackLocale]) && trim((string) $value[$fallbackLocale]) !== '') {
          return $value[$fallbackLocale];
        }

        $first = reset($value);

        return is_string($first) ? $first : null;
      }

      return $value;
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

    /**
     * Normalize available regions list for hreflang usage.
     */
    protected function resolveAvailableRegions(): array
    {
      $regions = $this->available_regions ?? null;

      if (is_string($regions)) {
        $decoded = json_decode($regions, true);
        $regions = json_last_error() === JSON_ERROR_NONE ? $decoded : [$regions];
      }

      if (!is_array($regions) || empty($regions)) {
        $fallback = $this->country_code ? [strtolower((string) $this->country_code)] : [];
        return $fallback;
      }

      $normalized = array_map(function ($value) {
        return strtolower(trim((string) $value));
      }, $regions);

      return array_values(array_unique(array_filter($normalized)));
    }

    /**
     * Resolve seo data including optional canonical flag.
     */
    protected function resolveSeo(): array
    {
      $seoRaw = $this->seoArray ?? null;

      if (is_null($seoRaw)) {
        $seoRaw = $this->seo ?? null;
      }

      $seo = $this->normalizeJsonPayload($seoRaw);
      $extras = $this->normalizeJsonPayload($this->extras ?? null);

      $disable = $seo['disable_base_canonical'] ?? ($extras['disable_base_canonical'] ?? false);

      return [
        'meta_title' => $seo['meta_title'] ?? null,
        'meta_description' => $seo['meta_description'] ?? null,
        'disable_base_canonical' => (bool) $disable,
      ];
    }

    /**
     * Normalize mixed JSON columns (arrays/objects/strings) into arrays.
     */
    protected function normalizeJsonPayload($payload): array
    {
      if ($payload instanceof \JsonSerializable) {
        $payload = $payload->jsonSerialize();
      } elseif (is_object($payload)) {
        $payload = (array) $payload;
      }

      if (is_string($payload)) {
        $decoded = json_decode($payload, true);
        if (json_last_error() === JSON_ERROR_NONE) {
          $payload = $decoded;
        }
      }

      return is_array($payload) ? $payload : [];
    }

    /**
     * Extract base modification slug from modifications list.
     */
    protected function resolveBaseModificationSlug($mods): ?string
    {
      $baseModel = $this->base ?? null;

      if ($baseModel && isset($baseModel->slug)) {
        return $baseModel->slug;
      }

      $collection = $mods instanceof Collection ? $mods : collect($mods ?? []);

      if ($collection->isEmpty()) {
        return $this->slug ?? null;
      }

      $base = $collection->first();
      $slug = null;

      if (is_array($base)) {
        $slug = $base['slug'] ?? null;
      } elseif (is_object($base)) {
        $slug = $base->slug ?? null;
      }

      return $slug ?? $this->slug ?? null;
    }
}
