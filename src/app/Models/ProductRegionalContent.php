<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRegionalContent extends Model
{
    use CrudTrait;
    use HasFactory;
    use HasTranslations;
    use FormatsUniqAttribute;

    protected $table = 'ak_product_regional_contents';

    protected $guarded = ['id'];

    protected $casts = [
        'content' => 'array',
        'excerpt' => 'array',
        'merchant_content' => 'array',
    ];

    protected $fillable = [
        'product_id',
        'country_code',
        'content',
        'excerpt',
        'merchant_content',
    ];

    protected $translatable = ['content', 'excerpt', 'merchant_content'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUniqStringAttribute(): string
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            strtoupper((string) $this->country_code),
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            strtoupper((string) $this->country_code),
        ]);

        return $this->formatUniqHtml($headline, [
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
        ]);
    }
}
