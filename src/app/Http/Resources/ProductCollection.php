<?php
 
namespace Backpack\Store\app\Http\Resources;
 
class ProductCollection extends BaseCollection
{
  private $total, $last_page, $current_page, $per_page, $resource_class;  

  public function __construct($resource, $options = null)
  {

    $this->resource_class = $options['resource_class'] ?? config('backpack.store.product.resource.small', 'Backpack\Store\app\Http\Resources\ProductSmallResource');

    $this->total = $resource->total();
    $this->last_page = $resource->lastPage();
    $this->current_page = $resource->currentPage();
    $this->per_page = $resource->perPage();

    $resource = $resource->getCollection();

    parent::__construct($resource);
  }

  /**
   * Transform the resource collection into an array.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return array
   */
  public function toArray($request)
  {
    return [
      'data' => $this->resource_class::collection($this->collection),
      'meta' => [
        'total' => $this->total,
        'current_page' => $this->current_page,
        'per_page' => $this->per_page,
        'last_page' => $this->last_page
      ]
    ];
  }
}