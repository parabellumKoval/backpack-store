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
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'rating' => $this->rating,
        'in_stock' => $this->simpleInStock,
        'image' => $this->image,
        'excerpt' => substr(strip_tags($this->content), 0, 500).'...',
        'modifications' => $this->modifications && $this->modifications->count()? 
          self::$resources['product']['tiny']::collection($this->modifications): 
            null
      ];
    }
}
