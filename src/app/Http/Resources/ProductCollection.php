<?php
 
namespace Backpack\Store\app\Http\Resources;
 
use Illuminate\Http\Resources\Json\ResourceCollection;
 
class ProductCollection extends ResourceCollection
{
  private $product_small_resource_class = 'Backpack\Store\app\Http\Resources\ProductSmallResource';
  private $total, $last_page, $current_page, $per_page;  

  public function __construct($resource)
  {
    $this->product_small_resource_class = config('backpack.store.product_small_resource', 'Backpack\Store\app\Http\Resources\ProductSmallResource');

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
      'data' => $this->product_small_resource_class::collection($this->collection),
      'meta' => [
        'total' => $this->total,
        'current_page' => $this->current_page,
        'per_page' => $this->per_page,
        'last_page' => $this->last_page
      ]
    ];
  }
}