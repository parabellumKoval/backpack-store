<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;

class BoughtTogether extends Model
{
    protected $table = 'ak_bought_together';
    protected $fillable = ['product_id','with_product_id','country_code','score'];
}
