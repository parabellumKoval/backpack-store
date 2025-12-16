<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Http\Resources\CategorySmallResource;
class CategoryLargeResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      $country = $request->input('country') ?? \Store::country();
      $childrenCollection = $this->resource->childrenForCountry($country, true);
      $parentBranch = $this->formatParentBranch($country);

      $parentNode = $this->getParentNode();
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'content' => $this->content,
        'excerpt' => $this->excerpt,
        // 'extras' => $this->extrasToArray,
        'extras' => $this->extras,
        'images' => $this->getImageSourcesForApi(),
        'children' => $childrenCollection->isEmpty()
          ? []
          : CategorySmallResource::collection($childrenCollection)->resolve($request),
        // 'parent' => $parentBranch,
        'parent' => $parentNode->isEmpty()? []: CategoryTinyResource::collection($parentNode),
        // 'breadcrumbs' => $this->buildBreadcrumbTrail($parentBranch),
        'seo' => $this->seoToArray,
        'tags' => $this->resource->relationLoaded('tags')
          ? $this->tags->map(function ($tag) {
              return [
                'id' => $tag->id,
                'text' => $tag->text,
                'color' => $tag->color,
              ];
            })->values()
          : [],
        'available_regions' => $this->resolveAvailableRegions(),
      ];
    }



    /**
     * Build nested parent chain for breadcrumbs.
     *
     * @param  string|null  $country
     * @return array|null
     */
    protected function formatParentBranch(?string $country = null): ?array
    {
      $category = $this->resource;

      if (!$category || !method_exists($category, 'getParentNode')) {
        return null;
      }

      $parent = $category->parent;

      if (!$parent) {
        return null;
      }

      $parentNodes = $category->getParentNode($parent, null, $country);

      if (!$parentNodes || !$parentNodes->count()) {
        return null;
      }

      $branch = null;

      foreach ($parentNodes->reverse() as $node) {
        $branch = [
          'id' => $node->id,
          'name' => $node->name,
          'slug' => $node->slug,
          'parent' => $branch,
        ];
      }

      return $branch;
    }

    /**
     * Flatten parent branch into ordered breadcrumbs (without "Home").
     *
     * @param  array|null  $parentBranch
     * @return array
     */
    protected function buildBreadcrumbTrail(?array $parentBranch): array
    {
      $trail = [];
      $node = $parentBranch;

      while (is_array($node)) {
        $trail[] = [
          'id' => $node['id'] ?? null,
          'name' => $node['name'] ?? null,
          'slug' => $node['slug'] ?? null,
        ];
        $node = $node['parent'] ?? null;
      }

      $trail = array_values(array_filter($trail, function ($item) {
        return !empty($item['name']) || !empty($item['slug']);
      }));

      $trail = array_reverse($trail);

      $trail[] = [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
      ];

      return $trail;
    }

    /**
     * Normalize available region list for hreflang meta.
     */
    protected function resolveAvailableRegions(): array
    {
      $raw = $this->resource->countries ?? null;

      if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [$raw];
      }

      if (is_array($raw) && !empty($raw)) {
        return $this->normalizeRegions($raw);
      }

      return $this->fallbackRegions();
    }

    /**
     * Collect fallback regions from store settings when category is global.
     */
    protected function fallbackRegions(): array
    {
      if (!class_exists('Store')) {
        return [];
      }

      $countries = \Store::countries() ?? [];
      if (empty($countries)) {
        return [];
      }

      return $this->normalizeRegions(array_keys($countries));
    }

    /**
     * Normalize region codes to lower-case unique values.
     *
     * @param  array  $regions
     */
    protected function normalizeRegions(array $regions): array
    {
      $normalized = array_map(function ($value) {
        return strtolower(trim((string) $value));
      }, $regions);

      return array_values(array_unique(array_filter($normalized)));
    }
}
