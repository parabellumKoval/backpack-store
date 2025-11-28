<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ProductLink extends Model
{
    use FormatsUniqAttribute;

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

    public function getUniqStringAttribute(): string
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;
        $linkable = $this->relationLoaded('linkable') ? $this->getRelation('linkable') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->kind,
            sprintf('product #%s', $product?->name ?? $this->product_id ?? '?'),
            $linkable ? sprintf('%s #%s', class_basename($linkable), $linkable->getKey()) : sprintf('%s:%s', class_basename($this->linkable_type ?? 'model'), $this->linkable_id ?? '?'),
            sprintf('priority: %s', $this->priority ?? 0),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;
        $linkable = $this->relationLoaded('linkable') ? $this->getRelation('linkable') : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->kind,
        ]);

        return $this->formatUniqHtml($headline, [
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            $linkable ? sprintf('%s #%s', class_basename($linkable), $linkable->getKey()) : sprintf('%s:%s', class_basename($this->linkable_type ?? 'model'), $this->linkable_id ?? '?'),
            sprintf('priority: %s', $this->priority ?? 0),
        ]);
    }
}
