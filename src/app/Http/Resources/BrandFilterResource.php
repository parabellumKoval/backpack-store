<?php

namespace Backpack\Store\app\Http\Resources;

class BrandFilterResource extends BaseResource
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
        'image' => $this->image,
        'count' => $this->count
      ];
    }
}