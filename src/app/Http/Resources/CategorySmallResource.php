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
      $country = $request->input('country') ?? \Store::country();
      $childrenCollection = $this->resource->childrenForCountry($country, true);

      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'image' => $this->getFirstImageForApi(),
        'children' => $childrenCollection->isEmpty()
          ? []
          : static::collection($childrenCollection)->resolve($request),
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
