<?php

namespace Aimix\Shop\app\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AttributeModification extends Pivot
{
    protected $casts = [
      'value' => 'array'
    ];
    
    public function setValueAttribute($value)
    {
      if(!is_array($value)){
        $trimed_value = trim($value);
        
        if(!empty($trimed_value) || (string)$value === '0' )
          $this->attributes['value'] = json_encode($value);
      }else{
          $this->attributes['value'] = json_encode($value);
      }
    } 
}
