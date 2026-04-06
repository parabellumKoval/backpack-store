<?php

namespace Backpack\Store\app\Http\Resources;

class ProductKratomLargeResource extends ProductLargeResource
{
    protected function resolveDetailImages(): array
    {
      if (method_exists($this->resource, 'getImageSourcesForApi')) {
        $resolved = $this->resource->getImageSourcesForApi();
        if (is_array($resolved) && !empty($resolved)) {
          return $resolved;
        }
      }

      return [];
    }

    public function toArray($request)
    {
      $data = parent::toArray($request);
      $data['images'] = $this->resolveDetailImages();

      return $data;
    }
}
