<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;

class ProductLink extends Model
{
    protected $table = 'ak_product_links';
    protected $fillable = ['linkable_type','linkable_id','product_id','kind','priority'];

    // полиморфный якорь (товар, статья и т.д.)
    public function linkable()
    {
        return $this->morphTo();
    }

    // рекомендуемый товар
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /* Удобные скоупы */
    public function scopeKind($q, string $kind) { return $q->where('kind', $kind); }
    public function scopeForAnchor($q, $model)
    {
        return $q->where('linkable_type', get_class($model))
                 ->where('linkable_id', $model->getKey());
    }
}
