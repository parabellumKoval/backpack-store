<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ProductCountryOverride extends Model
{
    use FormatsUniqAttribute;

    protected $table = 'ak_product_country_overrides';
    public $timestamps = true;

    protected $fillable = [
        'product_id', 'country_code', 'price_override', 'currency_code', 'old_price_override'
    ];

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            sprintf('product #%s', $this->product_id ?? '?'),
            $this->country_code,
            $this->currency_code,
            $this->price_override !== null ? 'price: '.$this->price_override : null,
            $this->old_price_override !== null ? 'old: '.$this->old_price_override : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([
            '#'.$this->id,
            sprintf('%s · %s', $this->country_code ?? '-', $this->currency_code ?? ''),
        ]);

        return $this->formatUniqHtml($headline, [
            sprintf('product #%s', $this->product_id ?? '?'),
            $this->price_override !== null ? 'price: '.$this->price_override : null,
            $this->old_price_override !== null ? 'old: '.$this->old_price_override : null,
        ]);
    }
}
