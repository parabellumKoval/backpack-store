<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Services\Campaign\CampaignPayloadService;

class ProductSmallResource extends BaseResource
{
    protected function resolveProductAttrs(): array
    {
      $attrs = is_object($this->resource) && method_exists($this->resource, 'getAttribute')
        ? $this->resource->getAttribute('attrs')
        : null;

      if (is_array($attrs)) {
        return $attrs;
      }

      $properties = $this->properties ?? null;

      return is_array($properties) ? $properties : [];
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      // $variants = $this->modifications()->available()->exists()? $this->modifications()->available(): null;
      // $variants_resource = $variants? self::$resources['product']['tiny']::collection($variants): null;

      // $variants_resource = $this->modifications()->available()->get();
      // dd( $variants_resource);
      // $variants_resource = null;
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'base_price' => $this->base_price ?? ($this->campaign ? $this->old_price : $this->price),
        'campaign_discount_amount' => $this->campaign_discount_amount ?? 0,
        'campaign' => app(CampaignPayloadService::class)->make($this->campaign),
        'rating' => $this->rating,
        'inStock' => $this->in_stock,
        'store_only' => (bool) ($this->store_only ?? false),
        'storeOnly' => (bool) ($this->store_only ?? false),
        'image' => $this->image,
        'excerpt' => substr(strip_tags($this->content), 0, 500).'...',
        'modifications' => $this->resource_modifications
      ];
    }
}
