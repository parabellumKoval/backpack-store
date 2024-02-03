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

  protected $translatable = [];

  public function __construct () {
    // If translatable value available
    if(config('backpack.store.attribute.translatable_value', true)) {
      $this->translatable = ['value'];
    }
  }

}
