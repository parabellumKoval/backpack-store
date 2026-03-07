<?php

namespace Backpack\Store\app\Http\Resources;

use Backpack\Store\app\Services\Campaign\CampaignPayloadService;

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
        'id' => $this->product_id ?? $this->id,
        'name' => $this->name,
        'shortName' => $this->short_name,
        'slug' => $this->slug,
        'price' => $this->price,
        'oldPrice' => $this->old_price,
        'basePrice' => $this->base_price ?? ($this->campaign ? $this->old_price : $this->price),
        'campaignDiscount' => $this->campaign_discount_amount ?? 0,
        'campaign' => app(CampaignPayloadService::class)->make($this->campaign),
        'currency' => $this->currency,
        'rating' => $this->rating,
        'image' => $this->effective()->getFirstImageForApi(),
        'inStock' => $this->in_stock,
        'store_only' => (bool) ($this->store_only ?? false),
        'storeOnly' => (bool) ($this->store_only ?? false),
        'external' => $this->external ?? 0
      ];
    }
}
