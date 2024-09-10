<?php

namespace Backpack\Store\app\Http\Resources;

class ProductCartResource extends BaseResource
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
        'code' => $this->simpleCode,
        'short_name' => $this->short_name,
        'price' => $this->simplePrice,
        'old_price' => $this->simpleOldPrice,
        'image' => $this->image,
        'amount' => $this->amount
      ];
    }
}
