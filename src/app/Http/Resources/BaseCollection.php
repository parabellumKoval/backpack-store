<?php

namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base collection class.
 * 
 * NOTE: This class extends JsonResource instead of ResourceCollection to avoid
 * the resolve() issue in Laravel 11+ where ResourceCollection tries to call
 * resolve() on each item, which fails for Eloquent models with HasTranslations trait.
 * 
 * Subclasses should store collection in a private property and use it in toArray().
 */
class BaseCollection extends JsonResource
{
  use \Backpack\Store\app\Traits\Resources;

  protected $items;

  public function __construct($resource)
  {
    self::resources_init();
    $this->items = $resource;
    // Передаём null в parent, чтобы избежать вызова resolve() на моделях
    parent::__construct(null);
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
