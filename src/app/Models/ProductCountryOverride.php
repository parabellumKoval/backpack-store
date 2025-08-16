<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCountryOverride extends Model
{
    protected $table = 'ak_product_country_overrides';
    public $timestamps = true;

    protected $fillable = [
        'product_id', 'country_code', 'price_override', 'currency_code', 'old_price_override'
    ];
}
