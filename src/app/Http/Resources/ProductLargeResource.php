<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use Backpack\Store\app\Http\Resources\ProductTinyResource;

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
        'old_price' => $this->old_price,
        'images' => $this->images,
        'content' => $this->content,
        'category' => $this->category,
        'modifications' => $this->modifications->count()? $product_tiny_resource_class::collection($this->modifications): null
      ];
    }
}
