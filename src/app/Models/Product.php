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

// TRAITS
use App\Models\Traits\ProductModel as ProductModelTrait;

// MODELS
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;

// RESOURCES
use Backpack\Store\app\Http\Resources\AttributeProductResource;

class Product extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;

    use ProductModelTrait;
    use \Backpack\Store\app\Traits\Resources;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_products';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = [
      'code',
      'name',
      'short_name',
      'slug',
      'content',
      'excerpt',
      'images',
      'parent_id',
      'brand_id',
      'price',
      'old_price',
      'in_stock',
      'is_active',
      'seo',
      'extras',
      'extras_trans',

      'props',
    ];
    // protected $hidden = [];
    // protected $dates = [];
    protected $with = ['categories', 'ap'];
    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
    ];

    protected $fakeColumns = [
      'meta_description', 'meta_title', 'seo', 'extras_trans', 'extras', 'images'
    ];
    
    protected $translatable = ['name', 'short_name', 'content', 'extras_trans', 'seo'];
    
    public $images_array = [];
    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
        
    /**
     * __construct
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __construct(array $attributes = array()) {
      parent::__construct($attributes);
      self::resources_init();
    }
    
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
        
    /**
     * getImages
     *
     * @param  mixed $amount
     * @return array
     */
    public function getImages($amount = -1):array
    {
      if(!$this->images) {
        return [];
      }

      if($amount < 0) {
        return $this->images;
      }elseif($amount === 0) {
        return [];
      }else {
        return array_slice($this->images, 0, $amount);
      }
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    public function categories()
    {
      return $this->belongsToMany(Category::class, 'ak_category_product');
    }
    
    
    /**
     * brand
     *
     * @return void
     */
    public function brand()
    {
      return $this->belongsTo(Brand::class, 'brand_id');
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
    public function ap()
    {
      return $this->hasMany(AttributeProduct::class);
    }

    /**
     * AttributeValue
     *
     * @return void
     */
    public function av()
    {
      return $this->hasManyThrough(AttributeValue::class, AttributeProduct::class);
    }

    // public function attributes() 
    // {
    //   return $this->hasManyThrough(Attribute::class, AttributeProduct::class, 'product_id', 'id');
    // }
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
        
    /**
     * getCategoryAttribute
     * 
     * Get first category if exists
     *
     * @return void
     */
    public function getCategoryAttribute()
    {
      return $this->categories[0] ?? null;
    }

    
    public function getCategoryOrParentCategory() {
      if(!$this->category && $this->parent) {
        return $this->parent->category;
      }

      return $this->category;
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
      $base_path = config('backpack.store.product.image.base_path', '/');

      if(isset($this->image['src'])) {
        return $base_path . $this->image['src'];
      }else {
        return null;
      }
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
    
    /**
     * getBaseAttribute
     *
     * Return parent product if exists, Otherwise return self
     * 
     * @return Product
     */
    public function getBaseAttribute() {
      if($this->parent)
        return $this->parent;
      else
        return $this;
    }
        
    /**
     * getModificationsAttribute
     *
     * Return all product modifications includes self model
     * 
     * @return collection
     */
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
      else 
      {
        return collect([])->prepend($this);
      }
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
        'meta_title' => $this->seoDecoded->meta_title ?? null,
        'meta_description' => $this->seoDecoded->meta_description ?? null,
      ];
    }

    public function getSeoDecodedAttribute() {
      return !empty($this->seo)? json_decode($this->seo): null;
    }

    public function getExtrasTransDecodedAttribute() {
      return !empty($this->extras_trans)? json_decode($this->extras_trans): null;
    }
    
      
    /**
     * getAttributesAttribute
     *
     * Return attributes with pivot values for each product
     * 
     * @return array
     */
    // public function getPropertiesAttribute () {

    //   $attrs = [];

    //   for($i = 0; $i < $this->ap->count(); $i++) {
    //     $thisAttr = $this->ap[$i]->attribute;

    //     if($this->ap[$i]->attribute_value_id){
    //       $thisAttr->pivotValue[] = $this->ap[$i]->attribute_value;
    //     }elseif($this->ap[$i]->value) {
    //       $thisAttr->pivotValue = $this->ap[$i]->value;
    //     }

    //     if(!isset($attrs[$thisAttr->id])) {
    //       $resource = self::$resources['attribute']['product'];
    //       $attrs[$thisAttr->id] = new $resource($thisAttr);
    //     }
    //   }

    //   return array_values($attrs);
    // }

    // public function getPropertiesAttribute () {
    //   $attrs = [];

    //   for($i = 0; $i < $this->ap->count(); $i++) {
    //     $attribute = $this->ap[$i]->attribute;

    //     if(!isset($attrs[$attribute->id])) {
    //       $attrs[$attribute->id] = $attribute;
    //     }

    //     if($this->ap[$i]->attribute_value_id){
    //       $attrs[$attribute->id]->pivotValue[] = $this->ap[$i]->attribute_value;
    //     }elseif($this->ap[$i]->value) {
    //       $attrs[$attribute->id]->pivotValue = $this->ap[$i]->value;
    //     }
    //   }

    //   return array_values($attrs);
    // }


    public function getPropertiesAttribute () {
      $attrs = [];

      for($i = 0; $i < $this->ap->count(); $i++) {
        $attribute = $this->ap[$i]->attribute;

        if(!isset($attrs[$attribute->id])) {
          // Skip if this attributes denny to properties
          if(!$attribute->in_properties) {
            continue;
          };

          $attrs[$attribute->id] = [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'slug' => $attribute->slug,
            // 'defaultValue' => $attribute->default_value,
            'si' => $attribute->si,
            'type' => $attribute->type,
            'value' => null
          ];
        }

        if($this->ap[$i]->attribute_value_id){
          $attrs[$attribute->id]['value'][] = $this->ap[$i]->attribute_value;
        }elseif($this->ap[$i]->value) {
          $attrs[$attribute->id]['value'] = $this->ap[$i]->value;
        }elseif($this->ap[$i]->value_trans) {
          $attrs[$attribute->id]['value'] = $this->ap[$i]->value_trans;
        }
      }

      return array_values($attrs);
    }
    
    /**
     * getRatingDetailedAttribute
     *
     * @return void
     */
    // public function getReviewsRatingDetailesAttribute() {
    //   return [
    //     'reviews_count' => 0,
    //     'rating_count' => 0,
    //     'rating' => 0,
    //     'rating_detailes' => [
    //       'rating_5' => 0,
    //       'rating_4' => 0,
    //       'rating_3' => 0,
    //       'rating_2' => 0,
    //       'rating_1' => 0
    //     ],
    //   ];
    // }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

}