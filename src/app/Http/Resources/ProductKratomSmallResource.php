<?php

namespace Backpack\Store\app\Http\Resources;

class ProductKratomSmallResource extends ProductSmallResource
{
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

      $data['image'] = $this->resolveCardImage();
      $data['images'] = $this->resolveCardImages();
      $data['excerpt'] = null;
      $data['short_description'] = null;

      return $data + [
        'attrs' => $this->resolveProductAttrs(),
      ];
    }
}
