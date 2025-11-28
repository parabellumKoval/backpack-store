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
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class SupplierProduct extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

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
    protected $dates = ['checked_at'];

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
      $this->product_class = \Settings::get('dress.product.model', 'Backpack\Store\app\Models\Product');
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

    public function getUniqStringAttribute(): string
    {
        $supplier = $this->relationLoaded('supplier') ? $this->getRelation('supplier') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $supplier?->name ?? sprintf('supplier #%s', $this->supplier_id ?? '?'),
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            $this->code ? 'code: '.$this->code : null,
            $this->price !== null ? 'price: '.$this->price : null,
            'stock: '.($this->in_stock ?? 0),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $supplier = $this->relationLoaded('supplier') ? $this->getRelation('supplier') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            $supplier?->name ?? sprintf('supplier #%s', $this->supplier_id ?? '?'),
        ]);

        return $this->formatUniqHtml($headline, [
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            $this->code ? 'code: '.$this->code : null,
            $this->price !== null ? 'price: '.$this->price : null,
            'stock: '.($this->in_stock ?? 0),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
