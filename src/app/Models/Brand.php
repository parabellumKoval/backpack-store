<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\BrandFactory;
use ParabellumKoval\BackpackImages\Traits\HasImages;

// MODEL
use Backpack\Store\app\Model\Product;

class Brand extends Model
{
    use HasFactory;
    use CrudTrait;
   use Sluggable;
   use SluggableScopeHelpers;
   use HasTranslations;
    use HasImages;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_brands';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
    ];

    protected $fakeColumns = [
      'meta_description', 'meta_title', 'seo', 'extras', 'images'
    ];
    
    protected $translatable = ['name', 'content', 'seo'];
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function boot()
    {
        parent::boot();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return BrandFactory::new();
    }
        
    /**
     * clearGlobalScopes
     *
     * @return void
     */
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
        
    /**
     * sluggable
     *
     * @return array
     */
    public function sluggable():array
    {
      return [
        'slug' => [
          'source' => 'slug_or_name',
        ],
      ];
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'brands';
    }

    public static function imageFieldLabel(?string $attribute = null): string
    {
        return 'Изображения';
    }

    public static function imageFieldTabLabel(?string $attribute = null): string
    {
        return 'Изображения';
    }

    public static function imageFieldNewItemLabel(?string $attribute = null): string
    {
        return 'Добавить изображение';
    }

    // public static function imageFieldPrefix(): string
    // {
    //     $basePath = (string) \Settings::get('dress.brand.image.base_path', '');

    //     if ($basePath !== '') {
    //         return $basePath;
    //     }

    //     $provider = static::imageProviderName();
    //     $prefix = config("backpack-images.providers.$provider.url_prefix");

    //     if (is_string($prefix) && $prefix !== '') {
    //         return $prefix;
    //     }

    //     return config('backpack-images.default_url_prefix', '/');
    // }
    
    /**
     * toArray
     *
     * @return void
     */
    public function toArray(){
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
      ];    
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function products()
    {
      return $this->hasMany(Product::class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query){
      return $query->where('is_active', 1);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * getImageAttribute
     *
     * Get first image from images array of the product or get image from parent product if exists 
     * 
     * @return Array|null Image is array(src, alt, title, size) 
     */
    public function getImageAttribute()
    {
        return $this->getFirstImage();
    }
    
    /**
     * getImageSrcAttribute
     *
     * Get src url address from getImageAttribute method
     * 
     * @return string|null string is image src url
     */
    public function getImageSrcAttribute()
    {
        return $this->getFirstImageUrl();
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


    /**
     * getSeoAttribute
     *
     * Return SEO fields 
     * 
     * @return array(
     *  string meta_title,
     *  string meta_title,
     * )
     */
    public function getSeoArrayAttribute() {
      return [
        'h1' => $this->seoDecoded->h1 ?? null,
        'meta_title' => $this->seoDecoded->meta_title ?? null,
        'meta_description' => $this->seoDecoded->meta_description ?? null,
      ];
    }

    public function getSeoDecodedAttribute() {
      return !empty($this->seo)? json_decode($this->seo): null;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
