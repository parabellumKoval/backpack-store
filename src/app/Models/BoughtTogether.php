<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class BoughtTogether extends Model
{
    use FormatsUniqAttribute;

    protected $table = 'ak_bought_together';
    protected $fillable = ['product_id','with_product_id','country_code','score'];

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            '#'.$this->id,
            sprintf('product #%s', $this->product_id ?? '?'),
            sprintf('with #%s', $this->with_product_id ?? '?'),
            $this->country_code,
            $this->score !== null ? 'score: '.$this->score : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = sprintf(
            '#%s - %s <-> %s',
            $this->id,
            $this->product_id ?? '?',
            $this->with_product_id ?? '?'
        );

        return $this->formatUniqHtml($headline, [
            $this->country_code,
            $this->score !== null ? 'score: '.$this->score : null,
        ]);
    }
}
