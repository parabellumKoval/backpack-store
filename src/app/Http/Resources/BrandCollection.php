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
    
    // Patterns
    $patterns = config('backpack.store.brands.alpha_groups.patterns', []);

    for($i = 0; $i < count($items); $i++){
      $symbol = mb_substr($items[$i]->name, 0, 1);

      if(is_numeric($symbol)){
        $symbol = '0-9';
      }else {
        $symbol = mb_strtolower($symbol, 'UTF-8');
      }

      // Try to distribute first symbol by patterns
      $have_fond = 0;
      for($j = 0; $j < count($patterns); $j++){
        preg_match($patterns[$j], $symbol, $find);

        if($find) {
          $collection[$j][$symbol][] = new $this->resource_class($items[$i]);
          $have_fond = 1;
        }
      }

      // If have not fond by patterns add to collection anyway
      if(!$have_fond) {
        $collection[count($patterns) + 1][$symbol][] = new $this->resource_class($items[$i]);
      }

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