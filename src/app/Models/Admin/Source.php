<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;

// MODEL
use Backpack\Store\app\Models\Source as BaseSource;

class Source extends BaseSource
{   
    /**
     * setRulesAttribute
     *
     * @param  mixed $values
     * @return void
     */
    public function setRulesAttribute($values) {
      // dd($values);
      $filterred_value = [];

      foreach($values as $value) {
        // clear by type
        if($value->type !== 'overprice') {
          $value->overprice = null;
        }

        // clear by target
        if($value->target !== 'brand') {
          $value->brands = null;
        }

        if($value->target !== 'code') {
          $value->codes = null;
        }

        if($value->target !== 'name') {
          $value->names = null;
        }

        if($value->target !== 'price') {
          $value->min_price = null;
          $value->max_price = null;
        }  
        
        $filterred_value[] = $value;
      }

      $this->attributes['rules'] = json_encode($filterred_value);
    }
}
