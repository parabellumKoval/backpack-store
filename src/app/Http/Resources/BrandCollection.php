<?php
 
namespace Backpack\Store\app\Http\Resources;
 
use Illuminate\Http\Resources\Json\ResourceCollection;
 
class BrandCollection extends ResourceCollection
{
  private $resource_class;  

  public function __construct($resource, Array $options)
  {
    $this->resource_class = $options['resource_class'];

    $items = $resource->all();
    $collection = [];
    
    for($i = 0; $i < count($items); $i++){
      $letter = mb_strtolower($items[$i]->name[0]);

      $collection[$letter][] = new $this->resource_class($items[$i]);
    }

    // Sorting by alphabet
    ksort($collection);

    parent::__construct($collection);
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
      'data' => $this->collection,
    ];
  }
}