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

// PIVOT
use Backpack\Store\app\Models\AttributeProduct;

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
    protected $fillable = ['props', 'images', 'price', 'old_price', 'is_active', 'code', 'in_stock'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
    ];

    protected $fakeColumns = [
      'meta_description', 'meta_title', 'fields', 'extras', 'images'
    ];
    
    protected $translatable = ['name', 'short_name', 'content', 'fields'];
    
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

    // protected static function boot()
    // {
    //     parent::boot();
    // }
    
    // public function clearGlobalScopes()
    // {
    //     static::$globalScopes = [];
    // }
    
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
    
    /**
     * parent
     *
     * Return parent (base) product of modification
     * 
     * @return Product
     */
    public function parent()
    {
      return $this->belongsTo(self::class, 'parent_id');
    }
    
    /**
     * children
     * 
     * Return children products (modifications) of the base products 
     *
     * @return Collection<Product>
     */
    public function children()
    {
      return $this->hasMany(self::class, 'parent_id');
    }
        
    /**
     * orders
     *
     * @return void
     */
    public function orders()
    {
      $order_model = config('backpack.store.order_model', 'Backpack\Store\app\Models\Order');
      return $this->belongsToMany($order_model, 'ak_order_product');
    }
        
    /**
     * attrs
     *
     * @return void
     */
    public function attrs()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Attribute', 'ak_attribute_product')->withPivot('value')->using(AttributeProduct::class);
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    
    /**
     * scopeInStock
     *
     * Return only products that stock quantity is 1 or more
     * 
     * @param  mixed $query
     * @return void
     */
    public function scopeInStock($query)
    {
      return $query->where('in_stock', '>=', 1);
    }

    /**
     * scopeActive
     *
     * Return only active products
     * 
     * @param  mixed $query
     * @return void
     */
    public function scopeActive($query)
    {
      return $query->where('is_active', 1);
    }
    
    /**
     * scopeBase
     *
     * Return only base products (not modifications).
     * Base products hasn't parent product 
     * 
     * @param  mixed $query
     * @return void
     */
    public function scopeBase($query)
    {
      return $query->where('parent_id', null);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    public function getCategoryAttribute()
    {
      return !empty($this->categories) && $this->categories->count()? $this->categories[0]: null;
    }

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
    
    /**
     * getImageAttribute
     *
     * Get first image from images array of the product or get image from parent product if exists 
     * 
     * @return Array|null Image is array(src, alt, title, size) 
     */
    public function getImageAttribute() {
      $image = $this->images[0] ?? null;

      if(!$image && $this->parent)
        $image = $this->parent->image;

      return $image;
    }
    
    /**
     * getImageSrcAttribute
     *
     * Get src url address from getImageAttribute method
     * 
     * @return string|null string is image src url
     */
    public function getImageSrcAttribute() {
      return $this->image['src'] ?? null;
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

    public function getSeoToArrayAttribute() {
      $fields = !empty($this->fields)? json_decode($this->fields): null;

      return [
        'meta_title' => $fields->meta_title ?? null,
        'meta_description' => $fields->meta_description ?? null,
      ];
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

}