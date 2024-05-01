<?php

namespace Backpack\Store\app\Http\Resources;

class AttributeProductResource extends BaseResource
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
        'defaultValue' => $this->default_value,
        'si' => $this->si,
        'type' => $this->type,
        'value' => $this->pivotValue ?? null
      ];
    }
}
