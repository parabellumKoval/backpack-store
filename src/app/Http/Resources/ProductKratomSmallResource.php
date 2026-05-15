<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Support\Facades\DB;

class ProductKratomSmallResource extends ProductSmallResource
{
    protected static array $groupNameCache = [];

    protected function resolveGroupNameValue($name): ?string
    {
      if (is_string($name)) {
        return trim($name) !== '' ? $name : null;
      }

      if (!is_array($name)) {
        return null;
      }

      $locale = backpack_translatable_request_locale(null) ?? app()->getLocale();
      $fallbackLocale = config('app.fallback_locale');
      $locales = array_filter([$locale, $fallbackLocale]);

      foreach ($locales as $localeCode) {
        $value = $name[$localeCode] ?? null;

        if (is_string($value) && trim($value) !== '') {
          return $value;
        }
      }

      foreach ($name as $value) {
        if (is_string($value) && trim($value) !== '') {
          return $value;
        }
      }

      return null;
    }

    protected function resolveGroupName()
    {
      $groupId = (int) ($this->group_id ?? 0);
      $productId = (int) ($this->product_id ?? $this->id ?? 0);

      if ($groupId <= 0 || $groupId === $productId) {
        return null;
      }

      if (!array_key_exists($groupId, self::$groupNameCache)) {
        $name = DB::table('ak_products')->where('id', $groupId)->value('name');

        if (is_string($name) && trim($name) !== '') {
          $decoded = json_decode($name, true);
          self::$groupNameCache[$groupId] = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
            ? $decoded
            : $name;
        } else {
          self::$groupNameCache[$groupId] = null;
        }
      }

      return $this->resolveGroupNameValue(self::$groupNameCache[$groupId]);
    }

    protected function resolveCardImage(): ?array
    {
      if (method_exists($this->resource, 'getFirstImageForApi')) {
        $image = $this->resource->getFirstImageForApi();
        if (is_array($image) && !empty($image['src'])) {
          return $image;
        }
      }

      $images = $this->resolveCardImages();
      if (!empty($images[0]) && is_array($images[0]) && !empty($images[0]['src'])) {
        return $images[0];
      }

      return null;
    }

    protected function resolveCardImages(): array
    {
      if (method_exists($this->resource, 'getImageSourcesForApi')) {
        $images = $this->resource->getImageSourcesForApi();
        if (is_array($images) && !empty($images)) {
          return $images;
        }
      }

      return [];
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      $data = parent::toArray($request);
      $groupName = $this->resolveGroupName();

      if ($groupName) {
        $data['name'] = $groupName;
      }

      $data['image'] = $this->resolveCardImage();
      $data['images'] = $this->resolveCardImages();
      $data['excerpt'] = null;
      $data['short_description'] = null;

      return $data + [
        'attrs' => $this->resolveProductAttrs(),
      ];
    }
}
