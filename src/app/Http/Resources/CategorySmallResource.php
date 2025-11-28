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
        'image' => $this->getFirstImageForApi(),
        'children' => $this->resource->childrenForCountry($request->input('country') ?? \Store::country(), true),
        'extras' => $this->extras,
        'extras_trans' => $this->extrasTransDecoded,
        'tags' => $this->resource->relationLoaded('tags')
          ? $this->tags->map(function ($tag) {
              return [
                'id' => $tag->id,
                'text' => $tag->value,
              ];
            })->values()
          : [],
      ];
    }
}
