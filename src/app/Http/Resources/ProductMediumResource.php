<?php

namespace Backpack\Store\app\Http\Resources;

class ProductMediumResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      return [
        'id' => $this->product_id ?? $this->id,
        'name' => $this->name,
        'shortName' => $this->short_name,
        'slug' => $this->slug,
        'price' => $this->price,
        'oldPrice' => $this->old_price,
        'currency' => $this->currency,
        'rating' => $this->rating,
        'image' => $this->getFirstImageForApi(),
        'inStock' => $this->in_stock,
        // 'modifications' => $this->resource_modifications
      ];
    }
}
