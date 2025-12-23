<?php

namespace Backpack\Store\app\Http\Resources;

class ProductSimpleResource extends BaseResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
      $repr =  $this->active_modification ?? $this;

      return [
        'id' => $repr->id,
        'name' => $this->name,
        'slug' => $repr->slug,
        'store_only' => (bool) ($this->store_only ?? false),
        'storeOnly' => (bool) ($this->store_only ?? false),
      ];
    }
}
