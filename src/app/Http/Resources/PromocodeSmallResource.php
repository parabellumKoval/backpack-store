<?php

namespace Backpack\Store\app\Http\Resources;

class PromocodeSmallResource extends BaseResource
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
        'name' => $this->name,
        'value' => $this->value,
        'type' => $this->type,
        'status' => $this->status,
        'isValid' => $this->isValid,
        'created_at' => $this->created_at
      ];
    }
}
