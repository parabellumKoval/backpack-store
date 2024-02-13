<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\AttributeProductFactory;

// MODELS
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Product;

class AttributeProduct extends Pivot
{
  use HasFactory;

  /*
  |--------------------------------------------------------------------------
  | GLOBAL VARIABLES
  |--------------------------------------------------------------------------
  */

  protected $table = 'ak_attribute_product';
  
  protected $fillable = ['value', 'attribute_value_id', 'attribute_id', 'product_id', 'type'];

  protected $with = ['attribute', 'attribute_value', 'product'];

  // protected $guarded = ['id'];

  /*
  |--------------------------------------------------------------------------
  | FUNCTIONS
  |--------------------------------------------------------------------------
  */

  // public function __construct () {
  //   // If translatable value available
  //   if(config('backpack.store.attribute.translatable_value', true)) {
  //     $this->translatable = ['value'];
  //   }
  // }

  /**
   * Create a new factory instance for the model.
   *
   * @return \Illuminate\Database\Eloquent\Factories\Factory
   */
  protected static function newFactory()
  {
    return AttributeProductFactory::new();
  }


  /*
  |--------------------------------------------------------------------------
  | RELATIONS
  |--------------------------------------------------------------------------
  */
  
  public function attribute()
  {
    return $this->belongsTo(Attribute::class);
  }
  
  public function attribute_value()
  {
    return $this->belongsTo(AttributeValue::class);
  }
  
  public function product()
  {
    return $this->belongsTo(Product::class);
  }

}
