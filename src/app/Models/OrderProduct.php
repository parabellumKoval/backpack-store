<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;


// MODEL
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class OrderProduct extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_order_product';
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
    protected static function booted(): void
    {
        static::creating(function (self $orderProduct) {
            if (!$orderProduct->country_code) {
                $orderProduct->country_code = \Store::country();
            }
        });
    }

    protected static function boot()
    {
        parent::boot();
    }

    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function product()
    {
      return $this->belongsTo(Product::class);
    }

    public function order()
    {
      return $this->belongsTo(Order::class);
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
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $order?->code ?? sprintf('order #%s', $this->order_id ?? '?'),
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            sprintf('qty: %s', $this->amount ?? 0),
            $this->currency_code,
            $this->country_code,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
        ]);

        return $this->formatUniqHtml($headline, [
            $order?->code ?? sprintf('order #%s', $this->order_id ?? '?'),
            sprintf('qty: %s', $this->amount ?? 0),
            $this->currency_code,
            $this->country_code,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
