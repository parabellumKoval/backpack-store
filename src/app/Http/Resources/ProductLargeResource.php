<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use Backpack\Store\app\Http\Resources\ProductTinyResource;
use Backpack\Store\app\Http\Resources\AttributeSmallResource;
use Backpack\Store\app\Http\Resources\CategoryTinyResource;

class ProductLargeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      $product_tiny_resource_class = config('backpack.store.product_tiny_resource', 'Backpack\Store\app\Http\Resources\ProductTinyResource');

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
        'categories' => $this->categories && $this->categories->count()? CategoryTinyResource::collection($this->categories): null,
        'attrs' => $this->attrs && $this->attrs->count()? AttributeSmallResource::collection($this->attrs): null,
        'modifications' => $this->modifications && $this->modifications->count()? $product_tiny_resource_class::collection($this->modifications): null,
        'seo' => $this->seo
      ];
    }
}
