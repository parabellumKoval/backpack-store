<?php

namespace Backpack\Store\app\Http\Resources;

class BrandLargeResource extends BaseResource
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
        'images' => $this->images,
        'content' => $this->content,
        'extras' => $this->extras,
        'seo' => $this->seo
      ];
    }
}
