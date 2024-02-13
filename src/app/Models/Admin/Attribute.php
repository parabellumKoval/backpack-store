<?php

namespace Backpack\Store\app\Models\Admin;

use Backpack\Store\app\Models\Attribute as BaseAttribute;

use Backpack\Store\app\Models\AttributeValue;

class Attribute extends BaseAttribute
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */
    
    public $values_store = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
  
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
      
    /**
     * getInputValuesAttribute
     * 
     * Prepage values to Dashboard
     *
     * @return void
     */
    public function getInputValuesAttribute(){
      
      $values = array(
          'color' => null,
          'number' => [
            'step' => 0,
            'min' => '',
            'max' => ''
          ],
          'range' => [
            'step' => 0,
            'min' => '',
            'max' => ''
          ],
          'datetime' => [
            'datetime' => null,
            'date' => null,
            'daterange' => null,
          ],
          'select' => null,  
      );

      $this_values = json_decode($this->values);
    
      // type range
      if($this->type == 'range')
      {
        if(isset($this_values->step))
        $values['range']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['range']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['range']['max'] = $this_values->max;
        
      }
      // type color
      elseif($this->type == 'color')
      {
        $values['color'] = $this_values;
      }
      // type number
      elseif($this->type == 'number')
      {
        if(isset($this_values->step))
        $values['number']['step'] = $this_values->step;
        
        if(isset($this_values->min))
        $values['number']['min'] = $this_values->min;
        
        if(isset($this_values->max))
        $values['number']['max'] = $this_values->max;
      }
      // type datetime
      elseif($this->type == 'datetime')
      {
        if(isset($this_values) && $this_values == 'datetime')
        $values['datetime']['datetime'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'date')
        $values['datetime']['date'] = 'selected="selected"';
        
        if(isset($this_values) && $this_values == 'daterange')
        $values['datetime']['daterange'] = 'selected="selected"';
      }
      // type checkbox / radio / select
      elseif($this->type == 'select' || $this->type == 'checkbox' || $this->type == 'radio')
      {
        if(isset($this_values))
          $values['select'] = $this_values;  
      }

        return $values;
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    // public function setSiAttribute($value) {
    //   $this->extras['si'] = $value;
    // }

    // public function setDefaultValueAttribute($value) {
    //   $this->extras['default_value'] = $value;
    // }

    public function setValuesAttribute($values_array) {
      $this->values_store = $values_array;
    }

    // public function setSiAttribute($value){
    //   $requestValue = \Request::all()['value'];
    //   if(is_array($requestValue))
    //   $attr_value = collect(array_filter($requestValue))->toJson();
    //  else
    //   $attr_value = json_encode($requestValue);

    //   $this->attributes['value'] = $attr_value;
      
    // }

    // public function setValuesAttribute($value) {
    //   $this->setTranslation('values', 'en', json_encode($value));
    //   //dd($value);
    // }

    // public function setTypeAttribute($value){
    //   $this->attributes['type'] = $value['type'];
    //   //$this->attributes['values'] = isset($value['values']) ? json_encode($value['values']) : null;

    //   if(isset($value['values'])) {
    //     //$this->setTranslation('values', $this->getCurrentLang(), json_encode($value['values']));
    //     //dd($value['values']);
    //     //$this->values = $value['values'];
    //   }
    // }
}
