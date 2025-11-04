<?php

namespace Backpack\Store\app\Http\Resources;

class ProductListResource extends \Backpack\Store\app\Http\Resources\BaseResource
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
        'id' => $this->product_id,
        'name' => $this->name,
        'slug' => $this->slug,
        'price' => $this->price,
        'oldPrice' => $this->old_price,
        'currency' => $this->currency,
        'rating' => $this->rating,
        // 'reviews_rating_detailes' => $this->reviewsRatingDetailes,
        'images' => $this->getImageSourcesForApi(2),
        'inStock' => $this->in_stock,
        'modifications' => $this->resource_modifications
      ];
    }
}
