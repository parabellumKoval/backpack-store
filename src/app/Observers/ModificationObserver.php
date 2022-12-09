<?php

namespace Aimix\Shop\app\Observers;

use Aimix\Shop\app\Models\Modification;

class ModificationObserver
{
    public function saved(Modification $modification){
	  //dd($modification->attributes_array);
	  $attributes = array_filter($modification->attributes_array, function($item){
		  return $item['value'] !== null;
	  });
/*
	  if(!count($attributes))
	  dd($attributes);
*/
	  
      $modification->attrs()->sync($attributes);
    }
    
}
