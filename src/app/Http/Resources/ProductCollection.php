<?php
 
namespace Backpack\Store\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
 
class ProductCollection extends JsonResource
{
  use \Backpack\Store\app\Traits\Resources;

  private $total, $last_page, $current_page, $per_page, $resource_class;
  private $items;

  public function __construct($resource, $options = null)
  {
    self::resources_init();

    $requestedResource = request()?->input('resource');
    $this->resource_class = $options['resource_class'] ?? self::resolveResourceClass('product', $requestedResource, 'small');

    $this->total = $resource->total();
    $this->last_page = $resource->lastPage();
    $this->current_page = $resource->currentPage();
    $this->per_page = $resource->perPage();

    $this->items = $resource->getCollection();

    // Передаём null в parent, чтобы избежать вызова resolve() на моделях
    parent::__construct(null);
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
      'data' => $this->resource_class::collection($this->items),
      'meta' => [
        'total' => $this->total,
        'current_page' => $this->current_page,
        'per_page' => $this->per_page,
        'last_page' => $this->last_page
      ]
    ];
  }
}
