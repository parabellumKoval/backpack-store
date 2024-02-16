<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeLargeResource extends JsonResource
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
        'values' => $this->values,
        'defaultValue' => $this->default_value,
        'si' => $this->si,
        'content' => $this->content,
        'type' => $this->type
      ];
    }
}