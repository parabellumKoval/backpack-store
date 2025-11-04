<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Http\Resources\AttributeProductResource;

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
        // 'categories' => $this->categories && $this->categories->count()? 
        //   self::$resources['category']['tiny']::collection($this->categories): 
        //     null,
        'attrs' => $this->properties,
        'custom_attrs' => $this->customProperties,
        'modifications' => $mods,
        'seo' => $this->seoArray
      ];
    }
}
