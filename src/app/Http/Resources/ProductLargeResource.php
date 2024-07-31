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
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'price' => $this->price,
        'code' => $this->code,
        'old_price' => $this->old_price,
        'rating' => $this->rating,
        'reviews_rating_detailes' => $this->reviewsRatingDetailes,
        'images' => $this->images,
        'content' => $this->content,
        'categories' => $this->categories && $this->categories->count()? 
          self::$resources['category']['tiny']::collection($this->categories): 
            null,
        'attrs' => $this->properties,
        'custom_attrs' => $this->customProperties,
        'modifications' => $this->modifications && $this->modifications->count()? 
          self::$resources['product']['tiny']::collection($this->modifications): 
            null,
        'seo' => $this->seoArray
      ];
    }
}
