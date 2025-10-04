<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;


class CurrencyRate extends Model
{
    use CrudTrait;

    protected $table = 'ak_currency_rates';
    protected $guarded = ['id'];

    protected $casts = [
        'rates' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function getRatesCountAttribute(): int
    {
        return is_array($this->rates) ? count($this->rates) : 0;
    }
}
