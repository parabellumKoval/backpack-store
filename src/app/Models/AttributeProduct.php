<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Relations\Pivot;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\AttributeProductFactory;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// MODELS
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Product;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class AttributeProduct extends Pivot
{
  use HasFactory;
  use CrudTrait;
  use HasTranslations;
  use FormatsUniqAttribute;

  /*
  |--------------------------------------------------------------------------
  | GLOBAL VARIABLES
  |--------------------------------------------------------------------------
  */

  protected $table = 'ak_attribute_product';
  
  protected $fillable = ['value', 'value_trans', 'attribute_value_id', 'attribute_id', 'product_id'];

  protected $with = ['attribute', 'attribute_value'];

  protected $translatable = ['value_trans'];

  /*
  |--------------------------------------------------------------------------
  | FUNCTIONS
  |--------------------------------------------------------------------------
  */

  /**
   * Create a new factory instance for the model.
   *
   * @return \Illuminate\Database\Eloquent\Factories\Factory
   */
  protected static function newFactory()
  {
    return AttributeProductFactory::new();
  }

  public function toArray()
  {
    return [
      'id' => $this->id,
    ];
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

  /*
  |--------------------------------------------------------------------------
  | ACCESSORS
  |--------------------------------------------------------------------------
  */

  public function getUniqStringAttribute(): string
  {
      $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;
      $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;
      $attributeValue = $this->relationLoaded('attribute_value') ? $this->getRelation('attribute_value') : null;

      $valueLabel = $this->value_trans ?? $this->value ?? $attributeValue?->value;

      return $this->formatUniqString([
          '#'.$this->id,
          $valueLabel,
          $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
          $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
      ]);
  }

  public function getUniqHtmlAttribute(): string
  {
      $attribute = $this->relationLoaded('attribute') ? $this->getRelation('attribute') : null;
      $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;
      $attributeValue = $this->relationLoaded('attribute_value') ? $this->getRelation('attribute_value') : null;

      $valueLabel = $this->value_trans ?? $this->value ?? $attributeValue?->value;
      $headline = $this->formatUniqString([
          '#'.$this->id,
          $valueLabel,
      ]);

      return $this->formatUniqHtml($headline, [
          $attribute?->name ?? sprintf('attribute #%s', $this->attribute_id ?? '?'),
          $attributeValue?->value ? 'option: '.$attributeValue->value : null,
          $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
      ]);
  }

  // public function getValueAttribute() {

  // }
}
