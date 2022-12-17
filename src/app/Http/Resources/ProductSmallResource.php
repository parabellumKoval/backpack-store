<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductSmallResource extends JsonResource
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
        'old_price' => $this->old_price,
        'image' => count($this->images)? $this->images[0]: null,
        'excerpt' => substr(strip_tags($this->content), 0, 500).'...',
        'modifications' => $this->modifications->count()? $product_tiny_resource_class::collection($this->modifications): null
      ];
    }
}
