<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class OrderInvoice extends Model
{
    use FormatsUniqAttribute;

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

    public function getUniqStringAttribute(): string
    {
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->template,
            $order?->code ?? sprintf('order #%s', $this->order_id ?? '?'),
            $this->locale,
            $this->currency,
            $this->generated_at ? $this->generated_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $order = $this->relationLoaded('order') ? $this->getRelation('order') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->template,
        ]);

        return $this->formatUniqHtml($headline, [
            $order?->code ?? sprintf('order #%s', $this->order_id ?? '?'),
            $this->locale,
            $this->currency,
            $this->generated_at ? $this->generated_at->format('Y-m-d H:i') : null,
        ]);
    }
}
