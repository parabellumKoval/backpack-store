<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\PromocodeFactory;

// TRAITS
use App\Models\Traits\PromocodeModel as PromocodeModelTrait;

// DATE
use Carbon\Carbon;

class Promocode extends Model
{
    use CrudTrait;
    use HasTranslations;
    use HasFactory;
    use PromocodeModelTrait;

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
    
    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return PromocodeFactory::new();
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
     * Return only active promocodes
     * 
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeActive($query)
    {
      return $query->where('is_active', 1);
    }
    
    /**
     * scopeValid
     * 
     * Return  only valid promocodes
     *
     * @param  Illuminate\Database\Eloquent\Builder $query
     * @return Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeValid($query) {
      return $query->where('is_active', 1)
                   ->where('valid_until', '>', Carbon::now())
                   ->whereColumn('limit', '>', 'used_times');
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */    

    public function getIsValidUntilAttribute() {
      return Carbon::now()->lt($this->valid_until);
    }

    public function getIsLimitAttribute() {
      return $this->limit !== 0 && $this->used_times >= $this->limit;
    }

    public function getStatusAttribute() {
      if($this->isLimit){
        return __('promocode.limit');
      }

      if(!$this->isActive) {
        return __('promocode.not_active');
      }

      if(!$this->isValidUntil) {
        return __('promocode.expired');
      }
    }

    /**
     * getIsValidAttribute
     * 
     * Get is valid attribute of the promocode (scopeValid analogue)
     *
     * @return boolean
     */
    public function getIsValidAttribute() {
      if($this->isLimit) {
        return false;
      }
  
      if(!$this->isActive) {
        return false;
      }
  
      if(!$this->isValidUntil) {
        return false;
      }

      return true;
    }
    

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

}