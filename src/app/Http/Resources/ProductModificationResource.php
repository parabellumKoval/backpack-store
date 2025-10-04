<?php

namespace Backpack\Store\app\Http\Resources;

class ProductModificationResource extends BaseResource
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
        'id' => $this->product_id,
        'short_name' => $this->short_name,
        'price' => $this->price,
        'oldPrice' => $this->old_price,
        'currency' => $this->currency,
        'inStock' => $this->in_stock,
        'passed' => $this->passedFilter
      ];
    }
}
