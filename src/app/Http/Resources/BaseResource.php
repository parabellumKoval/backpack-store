<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


class BaseResource extends JsonResource
{
  use \Backpack\Store\app\Traits\Resources;

  public function __construct($resource)
  {
    self::resources_init();
    parent::__construct($resource);
  }

  /**
   * Transform the resource into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  public function toArray($request)
  {
    return [];
  }
}