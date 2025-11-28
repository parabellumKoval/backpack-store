<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;


class MerchantCategory extends Model
{
  use CrudTrait;
  use FormatsUniqAttribute;

  /*
  |--------------------------------------------------------------------------
  | GLOBAL VARIABLES
  |--------------------------------------------------------------------------
  */

  protected $table = 'ak_merchant_categories';
  // protected $primaryKey = 'id';
  // public $timestamps = false;
  protected $guarded = ['id'];
  // protected $fillable = [];
  // protected $hidden = [];
  // protected $dates = [];

  protected $casts = [];

  /*
  |--------------------------------------------------------------------------
  | FUNCTIONS
  |--------------------------------------------------------------------------
  */
  protected static function boot()
  {
      parent::boot();
  }

    /**
     * toArray
     *
     * @return void
     */
    public function toArray(){
      return [
        'id' => $this->id,
        'key' => $this->key,
        'name' => $this->name,
      ];    
    }

  /*
  |--------------------------------------------------------------------------
  | RELATIONS
  |--------------------------------------------------------------------------
  */
  public function categories()
  {
    return $this->hasMany(Category::class);
  }


  /*
  |--------------------------------------------------------------------------
  | ACCESSORS
  |--------------------------------------------------------------------------
  */

  public function getUniqStringAttribute(): string
  {
      return $this->formatUniqString([
          '#'.$this->id,
          $this->key,
          $this->name,
      ]);
  }

  public function getUniqHtmlAttribute(): string
  {
      $headline = $this->formatUniqString([
          '#'.$this->id,
          $this->name,
      ]);

      return $this->formatUniqHtml($headline, [
          $this->key,
      ]);
  }
  
  public function getKeyNameAttribute() {
    return "{$this->key} - {$this->name}";
  }
  /*
  |--------------------------------------------------------------------------
  | MUTATORS
  |--------------------------------------------------------------------------
  */

}
