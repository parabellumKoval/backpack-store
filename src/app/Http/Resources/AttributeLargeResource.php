<?php

namespace Backpack\Store\app\Http\Resources;

class AttributeLargeResource extends BaseResource
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
