<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Support\Facades\DB;

// Stock events
use Backpack\Store\app\Events\SupplierProductSynced;

// SLUGS
use Cviebrock\EloquentSluggable\Sluggable;
use Cviebrock\EloquentSluggable\SluggableScopeHelpers;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\ProductFactory;

// TRAITS
// use Backpack\Store\app\Models\Traits\EffectiveProductTrait;
use Backpack\Store\app\Services\Variant\Vertical\EffectiveProduct;
use Backpack\Store\app\Models\Traits\MultistoreProductTrait;
use Backpack\Store\app\Models\Traits\TouchCatalogOnProductEvents;
use Backpack\Store\app\Models\Traits\UpsellProductTrait;

// MODELS
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Supplier;
use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\ProductRegionalContent;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

// RESOURCES
use Backpack\Store\app\Http\Resources\AttributeProductResource;

// CONTRACTS
use \Backpack\Store\app\Contracts\ProductService;
use \Backpack\Store\app\Services\Product\SupplierProductResolver;

// Images
use ParabellumKoval\BackpackImages\Traits\HasImages;

use Backpack\Store\app\Models\Traits\HasModification;
use Backpack\Tag\app\Traits\Taggable;
use Backpack\Reviews\app\Traits\Reviewable;


use Backpack\Reviews\app\Contracts\ReviewableAvailabilityScope;

class Product extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations {
        getTranslation as spatieGetTranslation;
    }
    use HasModification;

    use MultistoreProductTrait;
    // use SearchProductTrait;

    use TouchCatalogOnProductEvents;
    use UpsellProductTrait;

    use HasImages;

    use \Backpack\Store\app\Traits\Resources;
    use Taggable;
    use Reviewable;
    use FormatsUniqAttribute;

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
      'merchant_content',
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
      'modifications',
      'suppliersData',
      'props',
      'defaultSupplier',
      'defaultSupplierVirtual',
      'disabledRegions',
      'priceOverrides'
    ];
    // protected $hidden = [];
    // protected $dates = [];
    protected $with = ['categories', 'ap', 'suppliers', 'parent'];

    /**
     * Flag that prevents horizontal modification sync jobs during service merges.
     *
     * @var bool
     */
    public bool $skipServiceModificationSync = false;
    protected $casts = [
      'extras' => 'array',
      'images' => 'array',
    ];

    protected $fakeColumns = [
      'meta_description',
      'meta_title',
      'seo',
      'extras_trans',
      'extras',
      'images',
      // 'custom_attrs',
    ];
    
    protected $translatable = ['name', 'short_name', 'content', 'excerpt', 'merchant_content', 'extras_trans', 'seo'];
    protected const REGIONAL_CONTENT_FIELDS = ['content', 'excerpt', 'merchant_content'];
    
    public $images_array = [];
    
    private $available_languages = [];
    private $langs_list = [];

    private ?ProductService $productService = null;

    
    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public static function imageProviderName(?string $attribute = null): string
    {
        return 'bunny';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'products';
    }

    // public static function imageFieldPrefix(): string
    // {
    //     return (string) config('backpack-images.providers.bunny.pull_zone_url', '');
    // }

    /**
     * Get all category IDs including parent categories
     *
     * @return array Array of unique category IDs
     */
    public function getAllCategoryIds($countryCode = null): array
    {
      $categoryIds = [];

      $categories = $this->categories;

      foreach ($categories as $category) {
        $ancestors = $category
          ->getParentNode($category, null, $countryCode)
          ->pluck('id')
          ->toArray();

        if (!in_array($category->id, $ancestors, true)) {
          $ancestors[] = $category->id;
        }

        $categoryIds = array_merge($categoryIds, $ancestors);
      }

      return array_values(array_unique($categoryIds));
    }

    /**
     * Get product service instance
     */
    protected function productService(): ProductService
    {
        if (!$this->productService) {
            $this->productService = app(ProductService::class);
            $this->productService->setProduct($this);
        }
        return $this->productService;
    }

    /**
     * __construct
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __construct(array $attributes = array()) {
      parent::__construct($attributes);
      
      self::resources_init();

      // available languages
      $this->available_languages = config('backpack.crud.locales');
      $this->langs_list = array_keys($this->available_languages);
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
        'brand' => $this->brand,
        'rating' => $this->rating,
        'extras' => $this->extras,
        'images' => $this->images,
        'code' => $this->simpleCode,
        'in_stock' => $this->in_stock,
        // 'content' => nl2br($this->content),
        'uniq_title' => $this->uniqTitle,
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
    // public function getImages($amount = -1):array
    // {
    //   if(!$this->images) {
    //     return [];
    //   }

    //   if($amount < 0) {
    //     return $this->images;
    //   }elseif($amount === 0) {
    //     return [];
    //   }else {
    //     return array_slice($this->images, 0, $amount);
    //   }
    // }

    /**
     * syncSuppliers
     *
     * @param  mixed $ids
     * @param  mixed $detaching
     * @return void
     */
    public function syncSuppliers($data)
    {
        $result = $this->suppliers()->sync($data);

        // Диспатчим событие
        // В самом пакете оно не использует / обработчиков стандартных нет
        SupplierProductSynced::dispatch($this, $data);
    }

    
    /**
     * Method saveOriginalName
     *
     * @param $rewrite $rewrite [explicite description]
     *
     * @return void
     */
    public function saveOriginalName($rewrite = false){
      $names = $this->getTranslations('name');
      $ets = $this->getTranslations('extras_trans');

      $data = [];

      foreach($names as $lang => $name) {
        $extras_trans = $ets[$lang] ?? [];
        $extras_trans_array = !empty($extras_trans)? json_decode($extras_trans, true): [];

        if($rewrite === false && isset($extras_trans_array['original_name']) && !empty($extras_trans_array['original_name'])) {
          return;
        }else {
          $extras_trans_array['original_name'] = $name;
          $data[$lang] = $extras_trans_array;
        }
      }

      $multilangs_extras_trans = array_merge($ets, $data);
      
      $this->setTranslations('extras_trans', $multilangs_extras_trans);

    }


    public function setDataToExtras(string $key = null, $data = null) {
      if(!$key || !$data) return false;

      $extras = $this->extras;
      $extras[$key] = $data;
      $this->extras = $extras;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * categories
     *
     * @return void
     */
    public function categories()
    {
      return $this->belongsToMany(Category::class, 'ak_category_product');
    }
        
    /**
     * suppliers
     *
     * @return void
     */
    public function suppliers()
    {
      return $this->belongsToMany(Supplier::class, 'ak_supplier_product')
            ->withTimestamps()
            ->withPivot('in_stock', 'is_active', 'barcode', 'code', 'price', 'old_price', 'updated_at');
    }

    /**
     * sp
     *
     * @return void
     */
    public function sp(?string $country_code = null)
    {
      return $this->productService()->supplierProducts($country_code);
    }

    /**
     * catalog
     *
     * @return void
     */
    public function catalog()
    {
      return $this->hasOne(Catalog::class, 'parent_id');
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
      return $this->belongsTo(\Settings::get('dress.product.model', self::class), 'parent_id');
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
      return $this->hasMany(\Settings::get('dress.product.model', self::class), 'parent_id')
                  ->where('id', '!=', $this->id); // Предотвращаем циклические ссылки
    }

    public function regionalContents()
    {
      return $this->hasMany(ProductRegionalContent::class, 'product_id');
    }
        
    /**
     * orders
     *
     * @return void
     */
    public function orders()
    {
      $order_model = \Settings::get('dress.order.model', 'Backpack\Store\app\Models\Order');
      return $this->belongsToMany($order_model, 'ak_order_product');
    }
        
    /**
     * AttributeProduct
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
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeLeafs($query)
    {
        return $query->where(function($q) {
            $q->whereDoesntHave('children')  // products without modifications
              ->orWhere('parent_id', '!=', null);  // modifications themselves
        });
    }
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
     * scopeAvailable
     *
     * Return only Available products
     * 
     * @param  mixed $query
     * @return void
     */
    public function scopeAvailable($q, ?string $country = null)
    {
      return app(\Backpack\Store\app\Services\Product\AvailabilityFilter::class)
        ->scopeAvailable($q, $country);
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

    public function getUniqStringAttribute(): string
    {
        $brand = $this->relationLoaded('brand') ? $this->getRelation('brand') : null;
        $priceText = $this->price !== null
            ? sprintf('%s %s', number_format((float) $this->price, 2, '.', ' '), $this->currency_code ?? '')
            : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $this->name,
            'code: '.($this->code ?? $this->simpleCode ?? '-'),
            $brand?->name ?? sprintf('brand #%s', $this->brand_id ?? '?'),
            $priceText ? 'price: '.$priceText : null,
            sprintf('stock: %s', $this->in_stock ?? 0),
            sprintf('status: %s', ($this->is_active ?? false) ? 'active' : 'hidden'),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $brand = $this->relationLoaded('brand') ? $this->getRelation('brand') : null;
        $priceText = $this->price !== null
            ? sprintf('%s %s', number_format((float) $this->price, 2, '.', ' '), $this->currency_code ?? '')
            : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->name,
        ]);

        return $this->formatUniqHtml($headline, [
            'code: '.($this->code ?? $this->simpleCode ?? '-'),
            $brand?->name ?? sprintf('brand #%s', $this->brand_id ?? '?'),
            $priceText ? 'price: '.$priceText : null,
            sprintf('stock: %s', $this->in_stock ?? 0),
            sprintf('status: %s', ($this->is_active ?? false) ? 'active' : 'hidden'),
        ]);
    }

    
    public function getUniqTitleAttribute() {
      $brand_name = $this->brand->name ?? '-';
      return "id: {$this->id} | code: {$this->simpleCode} | brand: {$brand_name} ➡ {$this->name}";
    }
    

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

    public function getStoreOnlyAttribute(): bool
    {
        return $this->isStoreOnlyForCountry();
    }

        
    /**
     * getCategoryOrParentCategory
     *
     * @return void
     */
    public function getCategoryOrParentCategory() {
      if(!$this->category && $this->parent) {
        return $this->parent->category;
      }

      return $this->category;
    }

    protected function isStoreOnlyForCountry(?string $country = null): bool
    {
        $country = $this->resolveCountryCode($country);
        if (!$country) {
            return false;
        }

        $categories = $this->categories ?? [];

        foreach ($categories as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            $nodes = method_exists($category, 'getParentNode')
                ? $category->getParentNode($category, null, $country)
                : collect([$category]);

            foreach ($nodes as $node) {
                $list = $node->store_only_countries ?? [];

                if (is_string($list)) {
                    $decoded = json_decode($list, true);
                    $list = json_last_error() === JSON_ERROR_NONE ? $decoded : [$list];
                }

                if (is_array($list) && in_array($country, array_filter($list), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function resolveCountryCode(?string $country = null): ?string
    {
        $candidate = $country;

        if ($candidate === null && app()->bound('request')) {
            $requestCountry = request()->get('country');
            if ($requestCountry !== null && $requestCountry !== '') {
                $candidate = $requestCountry;
            }
        }

        if ($candidate === null) {
            $candidate = \Store::context()->country ?? null;
        }

        if ($candidate === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $candidate));

        return $normalized === '' ? null : $normalized;
    }
    
    /**
     * getImageAttribute
     *
     * Get first image from images array of the product or get image from parent product if exists 
     * 
     * @return Array|null Image is array(src, alt, title, size) 
     */
    // public function getImageAttribute() {
    //   $image = $this->images[0] ?? null;

    //   if(!$image && $this->parent)
    //     $image = $this->parent->image;

    //   return $image;
    // }
    
    // /**
    //  * getImageSrcAttribute
    //  *
    //  * Get src url address from getImageAttribute method
    //  * 
    //  * @return string|null string is image src url
    //  */
    // public function getImageSrcAttribute() {
    //   $base_path = \Settings::get('dress.product.image.base_path', '/');

    //   if(isset($this->image['src'])) {
    //     return $base_path . $this->image['src'];
    //   }else {
    //     return null;
    //   }
    // }
    
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
      $seo = $this->seoDecoded;
      $extras = $this->extras ?? [];
      if (is_string($extras)) {
        $decodedExtras = json_decode($extras, true);
        if (json_last_error() === JSON_ERROR_NONE) {
          $extras = $decodedExtras;
        } else {
          $extras = [];
        }
      }

      return [
        'meta_title' => $seo->meta_title ?? null,
        'meta_description' => $seo->meta_description ?? null,
        'disable_base_canonical' => (bool) ($extras['disable_base_canonical'] ?? ($seo->disable_base_canonical ?? false)),
      ];
    }
    
    /**
     * getSeoDecodedAttribute
     *
     * @return void
     */
    public function getSeoDecodedAttribute() {
      if (empty($this->seo)) {
        return null;
      }

      $seo = $this->seo;

      if (is_object($seo)) {
        return $seo;
      }

      if (is_array($seo)) {
        return json_decode(json_encode($seo));
      }

      if (is_string($seo)) {
        $decoded = json_decode($seo);

        if (json_last_error() === JSON_ERROR_NONE) {
          return $decoded;
        }
      }

      return null;
    }
    
    /**
     * getExtrasTransDecodedAttribute
     *
     * @return void
     */
    public function getExtrasTransDecodedAttribute() {
      return !empty($this->extras_trans)? json_decode($this->extras_trans): null;
    }
    

    /**
     * Method getAvailableProperties
     *
     * @return void
     */
    public function getAvailableProperties() {
      // create empty collection
      $attrs = collect();

      // if categories have not been set go out
      if(!$this->categories || !$this->categories->count())
        return;
      
      // 
      foreach($this->categories as $category) {
        
        $category_parent_node = $category->getParentNode();

        foreach($category_parent_node as $category) {
          // Take all active attributes for this category 
          $cat_attrs = $category->attributes()->active()->get();

          // If isset active attributes for this category merge with common list
          if($cat_attrs && $cat_attrs->count()) {
            $attrs = $attrs->merge($cat_attrs);
          }
        }
      }

      return $attrs->unique('id');
    }
    
    /**
     * Method getCountAvailablePropertiesAttribute
     *
     * @return void
     */
    public function getCountAvailablePropertiesAttribute() {
      $props = $this->getAvailableProperties();

      if(!$props) {
        return 0;
      }

      return $props->count();
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
    
    /**
     * getCustomPropertiesAttribute
     *
     * @return void
     */
    public function getCustomPropertiesAttribute() {
      if(empty($this->extras_trans)) {
        return null;
      }

      $extras = json_decode($this->extras_trans, true);

      if(!isset($extras['custom_attrs']) || empty($extras['custom_attrs'])) {
        return null;
      }
      
      if(is_array($extras['custom_attrs'])) {
        return $extras['custom_attrs'];
      }else {
        return json_decode($extras['custom_attrs'], true);
      }
    }

    /*
    |--------------------------------------------------------------------------
    | REGIONAL CONTENT
    |--------------------------------------------------------------------------
    */

    public function getTranslation(string $key, string $locale, bool $useFallbackLocale = true)
    {
      if ($this->isRegionalContentAttribute($key)) {
        $country = $this->normalizeCountryCode(\Store::context()->country ?? null);
        $value = $this->getRegionalContentValue($key, $country, $locale, $useFallbackLocale);

        if ($this->translationValueIsFilled($value)) {
          return $value;
        }
      }

      return $this->spatieGetTranslation($key, $locale, $useFallbackLocale);
    }

    public function getRegionalContentValue(string $attribute, ?string $countryCode = null, ?string $locale = null, bool $useFallbackLocale = true): ?string
    {
      if (!$this->isRegionalContentAttribute($attribute)) {
        return $this->spatieGetTranslation($attribute, $locale ?? app()->getLocale(), $useFallbackLocale);
      }

      $country = $this->normalizeCountryCode($countryCode ?? (\Store::context()->country ?? null));
      $translations = $this->getEffectiveRegionalizedTranslations($attribute, $country);

      if (!$translations) {
        return null;
      }

      $resolvedLocale = $locale
        ?? backpack_translatable_request_locale(null)
        ?? app()->getLocale()
        ?? config('app.fallback_locale');

      return $this->resolveTranslationFromArray($translations, $attribute, (string) $resolvedLocale, $useFallbackLocale);
    }

    public function getEffectiveRegionalizedTranslations(string $attribute, ?string $countryCode = null): ?array
    {
      if (!$this->isRegionalContentAttribute($attribute)) {
        return $this->getTranslations($attribute);
      }

      $country = $this->normalizeCountryCode($countryCode ?? (\Store::context()->country ?? null));
      $current = $this->resolveRegionalizedTranslationsForProduct($this, $attribute, $country);

      if ($this->translationsFilled($current)) {
        return $current;
      }

      if ($this->parent_id) {
        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        $fallback = $this->resolveRegionalizedTranslationsForProduct($parent, $attribute, $country);

        if ($this->translationsFilled($fallback)) {
          return $fallback;
        }
      }

      return $current ?: null;
    }

    public function getRegionalContentTranslationLocalesState(string $countryCode, string $attribute): array
    {
      $country = $this->normalizeCountryCode($countryCode);

      if (!$country || !$this->isRegionalContentAttribute($attribute)) {
        return [];
      }

      $translations = $this->getRegionalTranslationsForCountry($attribute, $country);
      $locales = $this->getTranslatableLocaleKeys();
      $state = [];

      foreach ($locales as $locale) {
        $value = $translations[$locale] ?? null;
        $length = $this->calculateTranslationValueLength($value);

        $state[$locale] = [
          'filled' => $length > 0,
          'length' => $length,
        ];
      }

      return $state;
    }

    public function getRegionalContentsFormValue(): array
    {
      $this->loadMissing('regionalContents');

      if (!$this->relationLoaded('regionalContents')) {
        return [];
      }

      $values = [];

      foreach ($this->regionalContents as $item) {
        $code = $this->normalizeCountryCode($item->country_code);

        if (!$code) {
          continue;
        }

        $values[$code] = [
          'content' => $item->getTranslations('content'),
          'excerpt' => $item->getTranslations('excerpt'),
          'merchant_content' => $item->getTranslations('merchant_content'),
        ];
      }

      return $values;
    }

    protected function resolveRegionalizedTranslationsForProduct(?self $product, string $attribute, ?string $countryCode): array
    {
      if (!$product) {
        return [];
      }

      $regionalTranslations = $this->getRegionalTranslationsForProduct($product, $attribute, $countryCode);
      $baseTranslations = $product->getTranslations($attribute);

      return $this->mergeTranslationArrays($regionalTranslations, $baseTranslations);
    }

    protected function getRegionalTranslationsForProduct(self $product, string $attribute, ?string $countryCode): array
    {
      $country = $this->normalizeCountryCode($countryCode);

      if (!$country || !$this->isRegionalContentAttribute($attribute)) {
        return [];
      }

      if ($product->relationLoaded('regionalContents')) {
        $regional = $product->regionalContents->firstWhere('country_code', $country);
      } else {
        $regional = $product->regionalContents()->where('country_code', $country)->first();
      }

      if (!$regional) {
        return [];
      }

      return $regional->getTranslations($attribute);
    }

    protected function getRegionalTranslationsForCountry(string $attribute, string $countryCode): array
    {
      return $this->getRegionalTranslationsForProduct($this, $attribute, $countryCode);
    }

    protected function mergeTranslationArrays(?array $primary, ?array $fallback): array
    {
      $primary = $this->normalizeTranslationArray($primary);
      $fallback = $this->normalizeTranslationArray($fallback);

      $merged = $primary;

      foreach ($fallback as $locale => $value) {
        if (!array_key_exists($locale, $merged)) {
          $merged[$locale] = $value;
        }
      }

      return $merged;
    }

    protected function normalizeTranslationArray($translations): array
    {
      if (!is_array($translations)) {
        return [];
      }

      $normalized = [];

      foreach ($translations as $locale => $value) {
        if (!is_string($locale) || $locale === '') {
          continue;
        }

        if ($this->calculateTranslationValueLength($value) === 0) {
          continue;
        }

        $normalized[$locale] = $value;
      }

      return $normalized;
    }

    protected function translationsFilled(?array $translations): bool
    {
      return $this->normalizeTranslationArray($translations) !== [];
    }

    protected function translationValueIsFilled($value): bool
    {
      return $this->calculateTranslationValueLength($value) > 0;
    }

    protected function resolveTranslationFromArray(array $translations, string $attribute, string $locale, bool $useFallbackLocale = true): ?string
    {
      $model = new ProductRegionalContent();

      $model->setTranslations($attribute, $translations);

      $value = $model->getTranslation($attribute, $locale, $useFallbackLocale);

      return $value === '' ? null : $value;
    }

    protected function isRegionalContentAttribute(?string $attribute): bool
    {
      if (!$attribute) {
        return false;
      }

      return in_array($attribute, self::REGIONAL_CONTENT_FIELDS, true);
    }

    protected function normalizeCountryCode(?string $countryCode): ?string
    {
      if (!is_string($countryCode)) {
        return null;
      }

      $normalized = strtolower(trim($countryCode));

      return $normalized === '' ? null : $normalized;
    }
    
    /**
     * getPropertiesAttribute
     *
     * @return void
     */
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
     * getCurrentSpAttribute
     *
     * @return void
     */
    // public function getCurrentSpAttribute() {
    //   $sp = $this->sp()
    //     ->where('is_active', 1)
    //     // reduce integer value to boolean
    //     ->orderByRaw('IF(in_stock > ?, ?, ?) DESC', [0, 1, 0])
    //     ->orderBy('price')
    //     ->first();

    //   return $sp;
    // }

    // public function effective(): EffectiveProduct
    // {
    //     return app(EffectiveProduct::class, ['p' => $this]);
    // }
    public function effective(bool $raw = false): EffectiveProduct
    {
        return app(EffectiveProduct::class, [
            'p' => $this,
            'raw' => $raw,
            'preferParent' => false,   // child-first
        ]);
    }

    public function inherited(bool $raw = false): EffectiveProduct
    {
        return app(EffectiveProduct::class, [
            'p' => $this,
            'raw' => $raw,
            'preferParent' => true,    // parent-first
        ]);
    }

    public function getSupplierAttribute() {
      return $this->supplierProduct->supplier;
    }

    public function getSupplierProductAttribute() {
      return app(SupplierProductResolver::class)->current($this);
    }

    public function getModificationsAttribute() {
      return app(\Backpack\Store\app\Contracts\Modification::class)->get($this);
    }

    public function getPriceAttribute() {
      return $this->productService()->price();
    }

    public function getCurrencyAttribute() {
      return $this->productService()->currency();
    }

    public function getOldPriceAttribute() {
      return $this->productService()->oldPrice();
    }

    public function getInStockAttribute() {
      return $this->supplierProduct->in_stock ?? 0;
    }

    public function getCodeAttribute() {
      if(!empty($this->attributes['code']))
        return $this->attributes['code'];

      $sp = $this->supplierProduct;

      if($sp) {
        if(!empty($sp->code)) {
          return $sp->code;
        }elseif(!empty($sp->barcode)) {
          return $sp->barcode;
        }else {
          return null;
        }
      }
    }

    public function getResourceModificationsAttribute()
    {
        $mods = $this->inherited()->modifications;
        if (!$mods || $mods->isEmpty()) {
            return null;
        }

        return $this->buildResourceModifications($mods);
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    public function setModificationsAttribute($value) {
      $this->modificationsToSave = $value;
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS METHODS
    |--------------------------------------------------------------------------
    */  

    /** Атомарно изменить остаток у актуального поставщика */
    public function adjustStock(int $delta): ?SupplierProduct
    {
        if (! $sp = $this->supplierProduct) {
            return null;
        }

        return DB::transaction(function () use ($sp, $delta) {
            // Обновляем прямо на уровне SQL, без гонок
            $newValue = max(0, $sp->in_stock + $delta);

            $sp->newQuery()
               ->whereKey($sp->getKey())
               ->update(['in_stock' => $newValue]);

            return $sp->refresh();
        });
    }

    /** Установить абсолютное значение остатка (>=0) */
    public function setStock(int $new): ?SupplierProduct
    {
        if (! $sp = $this->supplierProduct) {
            return null;
        }

        $new = max(0, $new);

        $sp->newQuery()
           ->whereKey($sp->getKey())
           ->update(['in_stock' => $new]);

        return $sp->refresh();
    }

    // public function setNameAttribute($v) {
    //   dd($v, $this->attributes);
    // }

}
