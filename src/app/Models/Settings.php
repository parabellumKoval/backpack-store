<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'ak_store_settings';
    public $timestamps = false;

    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'json'];

    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
}
