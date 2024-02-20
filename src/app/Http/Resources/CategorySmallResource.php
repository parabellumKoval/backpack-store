<?php

namespace Backpack\Store\app\Http\Resources;

class CategorySmallResource extends BaseResource
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
        'children' => $this->children()->orderBy('lft')->get()
      ];
    }
}
