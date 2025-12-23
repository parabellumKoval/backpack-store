<?php

namespace Backpack\Store\app\Http\Resources;

class ProductSmallResource extends BaseResource
{
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
