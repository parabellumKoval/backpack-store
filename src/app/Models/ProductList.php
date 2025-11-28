<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class ProductList extends Model
{
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;
    use FormatsUniqAttribute;

    protected $table = 'ak_product_lists';
    protected $fillable = [
        'page','slug','title','name','button_text','full_url','capacity',
        'sources','sort_order','filters','countries','is_active', 'lft', 'rgt'
    ];
    protected $casts = [
        'sources' => 'array',
        'sort_order' => 'array',
        'filters' => 'array',
        'countries' => 'array',
        'is_active' => 'boolean',
    ];

    protected $translatable = ['title', 'button_text'];

    // public function items()
    // {
    //     return $this->hasMany(ProductListItem::class, 'list_id');
    // }

    /** Активные для страны (если countries == null — доступно везде) */
    public function scopeActiveForCountry($q, ?string $country)
    {
        return $q->where('is_active', true)
                 ->where(function($q) use ($country) {
                     $q->whereNull('countries')
                       ->orWhereJsonContains('countries', $country);
                 });
    }

    
    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }

    /**
     * getSlugOrNameAttribute
     *
     * @return void
     */
    public function getSlugOrNameAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }
        return $this->name;
    }

    // public function getPrioritySourcesAttribute() {
    // }

    public function getUniqStringAttribute(): string
    {
        $countries = is_array($this->countries) ? implode(', ', $this->countries) : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->title ?? $this->name,
            $this->slug,
            $this->page,
            $countries ? 'countries: '.$countries : null,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $countries = is_array($this->countries) ? implode(', ', $this->countries) : null;
        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->title ?? $this->name,
        ]);

        return $this->formatUniqHtml($headline, [
            $this->slug,
            $this->page,
            $countries ? 'countries: '.$countries : null,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
        ]);
    }
}
