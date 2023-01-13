<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use Backpack\Store\app\Http\Resources\AttributeSmallResource;

class ProductTinyResource extends JsonResource
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
        'attrs' => $this->attrs && $this->attrs->count()? AttributeSmallResource::collection($this->attrs): null,
      ];
    }
}
