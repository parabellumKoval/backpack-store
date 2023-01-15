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
    protected $fillable = ['props'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
      //'seo' => 'array'
    ];
    protected $fakeColumns = [
      'meta_description', 'meta_title', 'seo', 'extras', 'images'
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
        'category' => $this->category,
        'categories' => $this->categories,
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
    
    public function categories()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Category', 'ak_category_product');
    }
    
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
    
    public function orders()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Order', 'ak_order_product');
    }
    
    public function attrs()
    {
        return $this->belongsToMany('Backpack\Store\app\Models\Attribute', 'ak_attribute_product')->withPivot('value');
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

    public function scopeBase($query)
    {
      return $query->where('parent_id', null);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getReviewsRatingDetailesAttribute() {
      $reviews = $this->reviews;

      //return  $reviews;
      $rating_1 = $reviews->where('rating', 1)->count();
      $rating_2 = $reviews->where('rating', 2)->count();
      $rating_3 = $reviews->where('rating', 3)->count();
      $rating_4 = $reviews->where('rating', 4)->count();
      $rating_5 = $reviews->where('rating', 5)->count();

      
      return [
        'reviews_count' => $reviews->count(),
        'rating_count' => $reviews->where('rating', '!==', null)->count(),
        'rating' => round($this->rating, 1),
        'rating_detailes' => [
          'rating_5' => $rating_5,
          'rating_4' => $rating_4,
          'rating_3' => $rating_3,
          'rating_2' => $rating_2,
          'rating_1' => $rating_1
        ]
      ];
    }

    public function getImageAttribute() {
      return $this->images && count($this->images)? $this->images[0]: null;
    }

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
      if($this->children->count())
      {
        $children = clone $this->children;
        return $children->prepend($this);
      }
      else if($this->parent)
      {
        $parent_children = clone $this->parent->children;
        return $parent_children->prepend($this->parent);
      }
    }
    
    public function getPropsAttribute() {
      // $attributes = $this->attrs;
      // $props = [];
      
      // foreach($attributes as $attribute){
      //   $values = json_decode($attribute->values);
      //   $props[$attribute->id] = $values[$attribute->pivot->value];
      // }

      // return $props;
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */


    public function setPropsAttribute($attributes) {
      $this->attrs()->detach();

      if(!$attributes)
        return;

      foreach($attributes as $attr_key => $value) {
        $clear_value = is_array($value)? array_filter($value, fn($i) => $i !== null): trim($value);
        $serialized_value = is_array($clear_value)? json_encode(array_values($clear_value)): $clear_value;
        
        if(empty($serialized_value))
          continue;

        $this->attrs()->attach($attr_key, ['value' => $serialized_value]);
      }
    }
}