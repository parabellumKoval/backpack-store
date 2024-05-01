<?php

namespace Backpack\Store\app\Http\Resources;

class ProductTinyResource extends BaseResource
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
        'short_name' => $this->short_name,
        'slug' => $this->slug,
        'price' => $this->price,
      ];
    }
}
