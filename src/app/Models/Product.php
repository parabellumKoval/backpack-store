<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\ProductFactory;


// REVIEWS
use Backpack\Reviews\app\Traits\Reviewable;

class Product extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;
    use Reviewable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_products';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    //protected $fillable = ['product_id', 'name', 'slug'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
      'seo' => 'array'
    ];
    protected $fakeColumns = [
      'seo', 'extras', 'images'
    ];
    
    protected $translatable = ['name', 'short_name', 'content', 'seo'];
    
    public $images_array = [];
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    
    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return ProductFactory::new();
    }

    protected static function boot()
    {
        parent::boot();
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
    
    public function toArray()
    {
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        // 'category' => $this->category,
        'price' => $this->price,
        'old_price' => $this->old_price,
        'is_active' => $this->is_active,
        'is_hit' => $this->is_hit,
        'rating' => $this->rating,
        'extras' => $this->extras,
        'images' => $this->images,
        'code' => $this->code,
        'in_stock' => $this->in_stock,
        'content' => nl2br($this->content),
      ];
    }
    

    
    public function sluggable():array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }
    
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    public function category()
    {
      return $this->belongsTo('Backpack\Store\app\Models\Category', 'category_id');
    }

    public function parent()
    {
      return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
      return $this->hasMany(self::class, 'parent_id');
    }

    // public function brand()
    // {
    //   return $this->belongsTo('\Aimix\Shop\app\Models\Brand');
    // }

    // public function reviews()
    // {
    //   return $this->hasMany(config('backpack.store.review_model', '\Backpack\Reviews\app\Models\Review'));
    // }
    
    public function orders()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Order', 'ak_order_product');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeActive($query)
    {
      return $query->where('is_active', 1);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getImageSrcAttribute() {
      if(isset($this->images[0]) && isset($this->images[0]['src']))
        return $this->images[0]['src'];
      else
        return null;
    }

    public function getSlugOrNameAttribute()
    {
        if ($this->slug != '') {
            return $this->slug;
        }
        return $this->name;
    }

    public function getIsBaseAttribute() {
      return !$this->parent? true: false;
    }

    public function getBaseAttribute() {
      if($this->parent)
        return $this->parent;
      else
        return $this;
    }
    
    public function getModificationsAttribute() {
      if($this->children->count()){
        $items = $this->children;
        $collection = $items->prepend($this);
        return $collection;
      }else if($this->parent){
        return $this->parent->children->prepend($this->parent);
      }
    }
      
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    // public function setNameAttribute() {
    //   dd($this);
    // }
}