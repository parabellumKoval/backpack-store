<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class AttributeProduct extends Pivot
{
  use HasTranslations;

  protected $table = 'ak_attribute_product';
  
  protected $guarded = ['id'];

  protected $translatable = ['value'];

    // protected $casts = [
    //   'value' => 'array'
    // ];
    
    // public function setValueAttribute($value)
    // {
    //   if(!is_array($value)){
    //     $trimed_value = trim($value);
        
    //     if(!empty($trimed_value) || (string)$value === '0' )
    //       $this->attributes['value'] = json_encode($value);
    //   }else{
    //       $this->attributes['value'] = json_encode($value);
    //   }
    // } 
}
