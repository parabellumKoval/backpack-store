<?php

namespace Backpack\Store\app\Http\Resources;

class CategoryLargeResource extends BaseResource
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
        'content' => $this->content,
        'excerpt' => $this->excerpt,
        // 'extras' => $this->extrasToArray,
        'extras' => $this->extras,
        'images' => $this->images,
        'children' => $this->children,
        'seo' => $this->seoToArray
      ];
    }
}
