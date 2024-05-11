<?php

namespace Backpack\Store\app\Http\Resources;

class BrandFilterTinyResource extends BaseResource
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
        'count' => $this->count
      ];
    }
}
