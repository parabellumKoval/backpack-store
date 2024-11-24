<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// Stock events
use Backpack\Store\app\Events\SupplierProductSaved;

// MODEL
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Supplier;

class SupplierProduct extends Model
{
    use CrudTrait;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_supplier_product';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [];

    protected $fakeColumns = [];
    
    protected $product_class = null;

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
     * syncSuppliers
     *
     * @param  mixed $ids
     * @param  mixed $detaching
     * @return void
     */
    public function saveWithEvent()
    {
        $this->save();
        SupplierProductSaved::dispatch($this);
    }
    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function product()
    {
      $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
      return $this->belongsTo($this->product_class);
    }

    public function supplier()
    {
      return $this->belongsTo(Supplier::class);
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

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
