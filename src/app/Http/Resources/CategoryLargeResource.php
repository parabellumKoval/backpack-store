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
        'images' => $this->getImageSourcesForApi(),
        'children' => $this->resource->childrenForCountry($request->input('country') ?? \Store::country(), true),
        'seo' => $this->seoToArray,
        'tags' => $this->resource->relationLoaded('tags')
          ? $this->tags->map(function ($tag) {
              return [
                'id' => $tag->id,
                'text' => $tag->text,
                'color' => $tag->color,
              ];
            })->values()
          : [],
      ];
    }
}
