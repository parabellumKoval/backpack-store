<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use \Backpack\Store\app\Models\Category;

class SeoPage extends Model
{
    use HasTranslations;
    use CrudTrait;

    protected $table = 'ak_seo_pages';
    protected $guarded = [];

    // локализуемые json-поля
    protected $translatable = ['h1','meta_title','meta_description','top_html','bottom_html'];

    protected $casts = [
        'is_active'       => 'bool',
        'show_on_category'=> 'bool',
        'show_on_product' => 'bool',
        'show_in_sitemap' => 'bool',
        'filters'         => 'array',
        'settings'        => 'array',
        'countries'       => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }

    public function scopeActive($q){ return $q->where('is_active',true); }
}
