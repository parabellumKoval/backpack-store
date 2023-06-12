<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

class Promocode extends Model
{
    use CrudTrait;
    use HasTranslations;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_promocodes';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    //protected $fillable = ['is_active'];
    // protected $hidden = [];
    protected $dates = ['valid_until'];
    // protected $with = ['categories', 'attrs'];
    
    protected $casts = [
      'extras' => 'array',
    ];

    protected $fakeColumns = [
      'extras'
    ];
    
    protected $translatable = ['name'];
  
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    
    public function toArray()
    {
      return [
        'id' => $this->id,
        'is_active' => $this->is_active,
        'code' => $this->code,
        'name' => $this->name,
        'value' => $this->value,
        'type' => $this->type,
      ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
        
    /**
     * orders
     *
     * @return void
     */
    public function orders()
    {
      $order_model = config('backpack.store.order_model', 'Backpack\Store\app\Models\Order');
      return $this->belongsToMany($order_model, 'ak_order_product');
    }
        
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */


    /**
     * scopeActive
     *
     * Return only active products
     * 
     * @param  mixed $query
     * @return void
     */
    public function scopeActive($query)
    {
      return $query->where('is_active', 1);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
      

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

}