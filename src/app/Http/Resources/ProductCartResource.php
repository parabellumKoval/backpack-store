<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductCartResource extends JsonResource
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
        'code' => $this->code,
        'short_name' => $this->short_name,
        'in_stock' => $this->in_stock,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'image' => $this->image,
        'amount' => $this->amount
      ];
    }
}
