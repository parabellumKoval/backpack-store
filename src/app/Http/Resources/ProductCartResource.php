<?php

namespace Backpack\Store\app\Http\Resources;

class ProductCartResource extends BaseResource
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
        'code' => $this->code,
        'short_name' => $this->short_name,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'image' => $this->image,
        'amount' => $this->amount
      ];
    }

    // public static function collection(mixed $resource){
    //   return $this->collection;
    // }
}
