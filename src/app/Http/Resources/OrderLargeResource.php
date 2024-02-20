<?php

namespace Backpack\Store\app\Http\Resources;

class OrderLargeResource extends BaseResource
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
        'code' => $this->code,
        'price' => $this->price,
        'status' => $this->status,
        'payStatus' => $this->pay_status,
        'deliveryStatus' => $this->delivery_status,
        //'orderable' => $this->orderable,
        'user' => $this->user,
        'delivery' => $this->delivery,
        'payment' => $this->payment,
        'products' => $this->productsAnyway,
        'created_at' => $this->created_at,
      ];
    }
}
