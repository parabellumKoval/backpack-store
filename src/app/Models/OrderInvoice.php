<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderInvoice extends Model
{
    protected $table = 'ak_order_invoices';

    protected $guarded = ['id'];

    protected $casts = [
        'meta' => 'array',
        'generated_at' => 'datetime',
        'qr_generated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
