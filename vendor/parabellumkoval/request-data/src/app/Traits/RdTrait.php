<?php
namespace Rd\app\Traits;

use Illuminate\Support\Facades\Validator;
use Rd\app\Exceptions\DetailedException;

trait RdTrait {
  
  public $rd_fields = null;

  /**
   * getFields
   *
   * @param  mixed $type
   * @return void
   */
  public function getFields($type = 'fields') {
    $fields = $this->rd_fields;
    
    if(!$fields)
      throw new \Exception('Please set fields in $this->rd_fields');
    else
      return $fields;
  }

  /** 
   *  Get validation rules from fields array
   * @param Array|String $fields
   * @return Array
  */
  public function getRules($fields = null, $type = 'fields') {
    $node = $fields? $fields: $this->rd_fields;

    $rules = [];
    
    if(is_string($node)) {
      return $node;
    }

    if(is_array($node)) {
      
      foreach($node as $field => $value) {
        if(in_array($field, ['store_in']))
          continue;
        
        $selfRules = $this->getRules($value);

        if(is_array($selfRules))
          foreach($selfRules as $k => $v) {
            if($k === 'rules') {
              $rules[$field] = $v;
            }else {
              $name = implode('.', [$field, $k]);
              $rules[$name] = $v;
            }
          }
        else
          $rules[$field] = $selfRules;
      }

    }

    return $rules;
  }
  
  /**
   * getFieldKeys
   *
   * @param  mixed $type
   * @return void
   */
  public function getFieldKeys($type = 'fields') {
    $keys = array_keys($this->rd_fields);
    $keys = array_map(function($item) {
      return preg_replace('/[\*\.]/u', '', $item);
    }, $keys);

    return $keys;
  }


  /**
   * validateData
   *
   * @param  array $data - Data from the order request
   * @return void
   */
  public function validateData($request) {
    $data = $request->only($this->getFieldKeys());

    // Apply validation rules to data
    $validator = Validator::make($data, $this->getRules());

    if ($validator->fails()) {
      $errors = $validator->errors()->toArray();
      $errors_array = [];

      foreach($errors as $key => $error){
        $this->assignArrayByPath($errors_array, $key, $error);
      }
      
      throw new DetailedException('Data Validation Error', 403, null, $errors_array);
    }

    return $data;
  }


  /**
   * setRequestFields
   * 
   * Automatycly setting all fields form request 
   * using structure from the config("backpack.store.order.fields").
   * 
   * 
   * @param  Backpack\Store\app\Models\Order $model - new Order model
   * @param  array $data - Order request data
   * @return Backpack\Store\app\Models\Order $model
   */
  protected function setRequestFields($model, array $data) {

    foreach($data as $field_name => $field_value){
      // Getting fields structure and rules from config
      $config_fields = $this->getFields();
      $field = $config_fields[$field_name] ?? $config_fields[$field_name.'.*'];
      
      // Skipping if filed is hidden
      if(isset($field['hidden']) && $field['hidden'])
        continue;

      // If JSON field 
      if(isset($field['store_in'])) {
        $field_old_value = $model->{$field['store_in']};
        $field_old_value[$field_name] = $field_value;
        $model->{$field['store_in']} = $field_old_value;
      }
      // if regular field
      else {
        $model->{$field_name} = $field_value;
      }
    }

    return $model;
  }

  
  /**
   * assignArrayByPath
   *
   * @param  mixed $arr
   * @param  mixed $path
   * @param  mixed $value
   * @param  mixed $separator
   * @return void
   */
  private function assignArrayByPath(&$arr, $path, $value, $separator='.') {
    $keys = explode($separator, $path);

    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }

    $arr = $value;
  }
}