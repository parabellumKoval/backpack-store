<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryLargeResource extends JsonResource
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
        'extras' => $this->extras,
        'images' => $this->images,
        'children' => $this->children,
        'h1' => $this->seo['h1'] ?? null,
        'meta_title' => $this->seo['meta_title'] ?? null,
        'meta_description' => $this->seo['meta_description'] ?? null,
      ];
    }
}
