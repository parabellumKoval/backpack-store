<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

use Backpack\Store\app\Models\Product as BaseProduct;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Attribute;

class Product extends BaseProduct
{
    public $props = null;
    public $modificationsToSave = [];
    public $suppliers_data = null;
    public $default_supplier = null;

    public $available_languages = [];
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
      
    /**
     * __construct
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __construct(array $attributes = array()) {
      parent::__construct($attributes);
      
      $langs = config('backpack.crud.locales');
      $this->available_languages = array_keys($langs);
    }
    
    /**
     * getCategoriesString
     *
     * @return void
     */
    public function getCategoriesString() {
      if(!$this->categories || !$this->categories->count())
        return '-';
        
      $cat_links = $this->categories->map(function($item) {
        $short_name = mb_substr($item->name, 0, 15);
        return "<a href='/admin/product?category={$item->id}'>{$short_name}</a>";
      });

      return implode(', ', $cat_links->toArray());
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function avsFake($value = null) {
      return new FakeRelation;
    }

    public function props($value = null) {
      return new FakeRelation;
    }

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
     * getAdminTranslationsAttribute
     *
     * @return void
     */
    public function getAdminTranslationsAttribute(){

      $translations = [];

      foreach($this->available_languages as $lang) {
        $content = $this->getTranslation('content', $lang, false);

        if(mb_strlen($content) > 150) {
          
          switch($lang) {
            case 'ru':
              $color = 'red';
              break;
            case 'uk':
              $color = 'blue';
              break;
            default:
              $color = 'black';
          }

          $translations[] = '<b style="color: ' . $color . '">' . mb_strtoupper($lang) . '</b>';
        }
      }

      $html = implode(', ', $translations);

      return $html;
    }
    
    /**
     * getAdminCodeAttribute
     *
     * @return void
     */
    public function getAdminCodeAttribute() {
      $supplier = $this->currentSp->supplier;

      $is_static_code = !empty($this->code)? true: false;

      $html = "<div>" . $this->simpleCode . "</div>";

      if($is_static_code) {
        $html .= "<b style='color: grey'>(САЙТ)</b>";
      }else if($supplier) {
        $html .= "<b style='color: " . $supplier->color . ";'>(" . $supplier->name . ")</b>";
      }

      return $html;
    }

    
    /**
     * getPropsAttribute
     *
     * @return void
     */
    public function getPropsAttribute() {
      // $attributes = $this->attrs;
      // $props = [];
      
      // foreach($attributes as $attribute){
      //   $values = json_decode($attribute->values);
      //   $props[$attribute->id] = $values[$attribute->pivot->value];
      // }

      // return $props;
    }
    
    /**
     * getInStockTotalSuppliersAttribute
     *
     * @return void
     */
    public function getInStockTotalSuppliersAttribute() {
      return $this->sp_sum_in_stock ?? 0;
      // return 0;
    }
    
    /**
     * getSuppliersDataAttribute
     *
     * @return void
     */
    public function getSuppliersDataAttribute() {
      $suppliers = $this->suppliers;
      
      $data_array = [];
      foreach($suppliers as $supplier) {
        $data_array[] = [
          'supplier' => $supplier->id,
          'code' => $supplier->pivot->code,
          'barcode' => $supplier->pivot->barcode,
          'in_stock' => $supplier->pivot->in_stock,
          'price' => $supplier->pivot->price,
          'old_price' => $supplier->pivot->old_price,
          'updated_at' => $supplier->pivot->updated_at->format('Y-m-d @ H:i:s'),
        ];
      }

      // dd($data_array);

      return $data_array;
    }
    
    /**
     * getDefaultSupplierAttribute
     *
     * @return void
     */
    public function getDefaultSupplierAttribute() {
      return $this->currentSp->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    
    /**
     * setPropsAttribute
     *
     * @param  mixed $attributes
     * @return void
     */
    public function setPropsAttribute($attributes) {
      //$this->attrs()->detach();
      if(!$attributes)
        return;

      
      foreach($attributes as $attr_key => $value) {
        $clear_value = is_array($value)? array_filter($value, fn($i) => $i !== null): trim($value);
        // $serialized_value = is_array($clear_value)? json_encode(array_values($clear_value)): $clear_value;
        
        $this->props[$attr_key] = $clear_value;

        // dd($clear_value);
        //$this->attrs()->attach($attr_key, ['value' => $serialized_value]);

        //syncWithoutDetaching
        // $this->attrs()->syncWithoutDetaching([
        //   $attr_key => ['value' => $serialized_value]
        // ]);

        // $this->ap()->
      }
    }

    
    /**
     * setModificationsAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setModificationsAttribute($value) {
      $this->modificationsToSave = $value;
    }

    /**
     * setSuppliersAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setSuppliersDataAttribute($value) {
      $this->suppliers_data = json_decode($value, true);
    }
    
    /**
     * setSupplierAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setDefaultSupplierVirtualAttribute($value) {
      $this->default_supplier = Request::input('defaultSupplier', []);
    }
}


class FakeRelation {
  public function sync($value){
    dd($value);
  }

  public function getRelated() {
    return new AttributeValue;
  }
}