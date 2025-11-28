<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\Helpers\Traits\FormatsUniqAttribute;


class CurrencyRate extends Model
{
    use CrudTrait;
    use FormatsUniqAttribute;

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

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            $this->source,
            'base: '.$this->base,
            sprintf('rates: %s', $this->rates_count),
            $this->fetched_at ? $this->fetched_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->source,
        ]);

        return $this->formatUniqHtml($headline, [
            'base: '.$this->base,
            sprintf('rates: %s', $this->rates_count),
            $this->fetched_at ? $this->fetched_at->format('Y-m-d H:i') : null,
        ]);
    }
}
