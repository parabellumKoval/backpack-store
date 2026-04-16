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
use Backpack\Store\database\factories\CategoryFactory;
use ParabellumKoval\BackpackImages\Traits\HasImages;
use Backpack\Tag\app\Traits\Taggable;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Backpack\Store\app\Models\Traits\HasFaqData;

class Category extends Model
{
    use HasFactory;
    use CrudTrait;
    use Sluggable;
    use SluggableScopeHelpers;
    use HasTranslations;
    use HasImages;
    use Taggable;
    use FormatsUniqAttribute;
    use HasFaqData;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_product_categories';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $fakeColumns = ['seo', 'extras', 'extras_trans', 'images', 'params'];
    protected $casts = [
        'params' => 'array',
        'extras' => 'array',
        'images' => 'array',
        'countries' => 'array',
        'store_only_countries' => 'array',
        'storefronts' => 'array',
        'is_active' => 'boolean',
    ];

    protected $translatable = ['name', 'content', 'seo', 'extras_trans'];

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
      return CategoryFactory::new();
    }

    public function toArray(){
      $tags = $this->relationLoaded('tags')
        ? $this->tags->map(function ($tag) {
            return [
              'id' => $tag->id,
              'text' => $tag->text,
              'color' => $tag->color,
            ];
          })->toArray()
        : [];
      return [
        'id' => $this->id,
        'name' => $this->name,
        'slug' => $this->slug,
        'children' => $this->childrenForCountry(null, true),
        'uniq_title' => $this->uniqTitle,
        'tags' => $tags,
        'extras' => $this->extras,
      ];    
    }
    
    public function clearGlobalScopes()
    {
        static::$globalScopes = [];
    }
    
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'slug_or_name',
            ],
        ];
    }

   public static function imageProviderName(?string $attribute = null): string
    {
        return 'bunny';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'categories';
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
    //     $basePath = (string) \Settings::get('dress.category.image.base_path', '');

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

    protected static function resolveCountry(?string $country = null, bool $fallbackToStore = false): ?string
    {
        if ($country !== null) {
            return static::normalizeCountryCode($country);
        }

        if (app()->bound('request')) {
            $requestCountry = request()->get('country');
            if ($requestCountry !== null && $requestCountry !== '') {
                return static::normalizeCountryCode($requestCountry);
            }
        }

        if ($fallbackToStore && class_exists(\Backpack\Store\app\Services\Store::class)) {
            $storeCountry = \Store::country();
            if ($storeCountry !== null && $storeCountry !== '') {
                return static::normalizeCountryCode($storeCountry);
            }
        }

        return null;
    }

    protected static function normalizeCountryCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = trim($code);

        return $normalized === '' ? null : $normalized;
    }

    protected static function resolveStorefront(?string $storefront = null, bool $fallbackToStore = false): ?string
    {
        if (!\Backpack\Store\app\Services\Store::isStorefrontEnabled()) {
            return null;
        }

        if ($storefront !== null) {
            return static::normalizeStorefrontCode($storefront);
        }

        $requestKey = \Backpack\Store\app\Services\Store::storefrontRequestKey();
        $headerName = \Backpack\Store\app\Services\Store::storefrontHeaderName();

        if (app()->bound('request')) {
            $requestStorefront = request()->get($requestKey) ?? request()->header($headerName);
            if ($requestStorefront !== null && $requestStorefront !== '') {
                return static::normalizeStorefrontCode($requestStorefront);
            }
        }

        if ($fallbackToStore && class_exists(\Backpack\Store\app\Services\Store::class)) {
            return \Backpack\Store\app\Services\Store::storefront();
        }

        return static::normalizeStorefrontCode(\Backpack\Store\app\Services\Store::defaultStorefront());
    }

    protected static function normalizeStorefrontCode(?string $code): ?string
    {
        return \Backpack\Store\app\Services\Store::normalizeStorefrontCode($code);
    }

    protected function prepareCountryCodes($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null && json_last_error() !== JSON_ERROR_NONE ? [$value] : $decoded;
        }

        return collect($value ?? [])
            ->filter(function ($code) {
                return $code !== null && $code !== '';
            })
            ->map(function ($code) {
                return static::normalizeCountryCode($code);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function prepareStorefrontCodes($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded === null && json_last_error() !== JSON_ERROR_NONE ? [$value] : $decoded;
        }

        return collect($value ?? [])
            ->filter(function ($code) {
                return $code !== null && $code !== '';
            })
            ->map(function ($code) {
                return static::normalizeStorefrontCode($code);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected static function countryCandidates(?string $country = null): array
    {
        $candidates = [];
        if ($country) {
            $candidates[] = $country;
        }

        // if (class_exists(\Backpack\Store\app\Services\Store::class)) {
        //     $global = static::normalizeCountryCode(\Store::globalRegion());
        //     if ($global) {
        //         $candidates[] = $global;
        //     }
        // }

        return array_values(array_unique(array_filter($candidates)));
    }

    public function scopeForCountry(Builder $query, ?string $country = null, bool $fallbackToStore = false)
    {
        $country = static::resolveCountry($country, $fallbackToStore);

        if (!$country) {
            return $query;
        }

        $candidates = static::countryCandidates($country);

        return $query->where(function (Builder $q) use ($candidates) {
            $q->whereNull('countries');
            foreach ($candidates as $candidate) {
                $q->orWhereJsonContains('countries', $candidate);
            }
        });
    }

    public function scopeActiveForCountry(Builder $query, ?string $country = null, bool $fallbackToStore = false)
    {
        return $query->active()->forCountry($country, $fallbackToStore);
    }

    public function isAvailableForCountry(?string $country = null, bool $fallbackToStore = false): bool
    {
        $country = static::resolveCountry($country, $fallbackToStore);

        if (!$country) {
            return true;
        }

        if (empty($this->countries)) {
            return true;
        }

        $available = array_map([static::class, 'normalizeCountryCode'], $this->countries);
        $available = array_filter($available);

        $candidates = static::countryCandidates($country);

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $available, true)) {
                return true;
            }
        }

        return false;
    }

    public function childrenForCountry(?string $country = null, bool $fallbackToStore = false, array $visited = [])
    {
        $country = static::resolveCountry($country, $fallbackToStore);
        $storefront = static::resolveStorefront(null, $fallbackToStore);
        $currentKey = $this->getKey();
        if ($currentKey !== null) {
            $visited[$currentKey] = true;
        }

        $childrenQuery = $this->children()
            ->active()
            ->orderBy('lft')
            ->with('tags');
        if ($country) {
            $childrenQuery->forCountry($country, false);
        }

        $children = $childrenQuery->get()->reject(function (self $child) use ($visited) {
            $childKey = $child->getKey();
            return $childKey !== null && isset($visited[$childKey]);
        })->filter(function (self $child) use ($country, $storefront) {
            return $child->isAvailableForStorefront($storefront, false)
                && (!$country || $child->isAvailableForCountry($country, false));
        });

        return $children->map(function (self $child) use ($country, $visited) {
            $child->setRelation('children', $child->childrenForCountry($country, true, $visited));
            return $child;
        });
    }

    protected function collectNodeIds(?string $country = null, bool $fallbackToStore = false): array
    {
        $country = static::resolveCountry($country, $fallbackToStore);

        if ($country && !$this->isAvailableForCountry($country, false)) {
            return [];
        }

        $ids = [$this->id];

        $childrenQuery = $this->children()->orderBy('lft');
        if ($country) {
            $childrenQuery->forCountry($country, false);
        }

        $children = $childrenQuery->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->collectNodeIds($country, false));
        }

        return array_values(array_unique($ids));
    }

    public function getCountriesListAttribute(): ?array
    {
        if (empty($this->countries)) {
            return null;
        }

        if (!class_exists(\Backpack\Store\app\Services\Store::class)) {
            return $this->countries;
        }

        $options = \Store::countryOptions();

        return array_values(array_map(function ($code) use ($options) {
            $code = static::normalizeCountryCode($code);
            return $options[$code] ?? $code;
        }, $this->countries));
    }

    public function setCountriesAttribute($value): void
    {
        $codes = $this->prepareCountryCodes($value);
        $this->attributes['countries'] = empty($codes) ? null : json_encode($codes);
    }

    public function getStoreOnlyCountriesListAttribute(): ?array
    {
        if (empty($this->store_only_countries)) {
            return null;
        }

        if (!class_exists(\Backpack\Store\app\Services\Store::class)) {
            return $this->store_only_countries;
        }

        $options = \Store::countryOptions();

        return array_values(array_map(function ($code) use ($options) {
            $code = static::normalizeCountryCode($code);
            return $options[$code] ?? $code;
        }, $this->store_only_countries));
    }

    public function setStoreOnlyCountriesAttribute($value): void
    {
        $codes = $this->prepareCountryCodes($value);
        $this->attributes['store_only_countries'] = empty($codes) ? null : json_encode($codes);
    }

    public function getStorefrontsListAttribute(): ?array
    {
        if (empty($this->storefronts)) {
            return null;
        }

        if (!class_exists(\Backpack\Store\app\Services\Store::class)) {
            return $this->storefronts;
        }

        $options = \Backpack\Store\app\Services\Store::storefrontOptions();

        return array_values(array_map(function ($code) use ($options) {
            $code = static::normalizeStorefrontCode($code);
            return $options[$code] ?? $code;
        }, $this->storefronts));
    }

    public function setStorefrontsAttribute($value): void
    {
        $codes = $this->prepareStorefrontCodes($value);
        $this->attributes['storefronts'] = empty($codes) ? null : json_encode($codes);
    }

    public function getEffectiveStorefrontsAttribute(): ?array
    {
        return $this->resolveEffectiveStorefronts();
    }

    protected function resolveEffectiveStorefronts(): ?array
    {
        if (!\Backpack\Store\app\Services\Store::isStorefrontEnabled()) {
            return null;
        }

        $current = $this;

        while ($current instanceof self) {
            $list = $current->storefronts ?? [];

            if (is_string($list)) {
                $decoded = json_decode($list, true);
                $list = json_last_error() === JSON_ERROR_NONE ? $decoded : [$list];
            }

            $list = $this->prepareStorefrontCodes($list);
            if (!empty($list)) {
                return $list;
            }

            $current = $current->relationLoaded('parent')
                ? ($current->getRelation('parent') instanceof self ? $current->getRelation('parent') : null)
                : $current->parent()->first();
        }

        if (\Backpack\Store\app\Services\Store::applyUnassignedCategoriesToDefaultStorefront()) {
            return [\Backpack\Store\app\Services\Store::defaultStorefront()];
        }

        return null;
    }

    public function isAvailableForStorefront(?string $storefront = null, bool $fallbackToStore = false): bool
    {
        $storefront = static::resolveStorefront($storefront, $fallbackToStore);

        if (!$storefront) {
            return true;
        }

        $allowed = $this->resolveEffectiveStorefronts();

        if ($allowed === null) {
            return true;
        }

        return in_array($storefront, $allowed, true);
    }

    public static function visibleIdsForContext(?string $country = null, ?string $storefront = null, bool $fallbackToStore = true): array
    {
        $country = static::resolveCountry($country, $fallbackToStore);
        $storefront = static::resolveStorefront($storefront, $fallbackToStore);

        $query = static::query()
            ->select(['id', 'parent_id', 'countries', 'storefronts', 'is_active'])
            ->active();

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $rowsById = [];
        $childrenByParent = [];
        $visibleIds = [];
        $defaultStorefront = \Backpack\Store\app\Services\Store::defaultStorefront();
        $defaultOnly = \Backpack\Store\app\Services\Store::applyUnassignedCategoriesToDefaultStorefront();
        $visited = [];

        foreach ($rows as $row) {
            $rowId = (int) $row->id;
            $parentId = $row->parent_id !== null ? (int) $row->parent_id : null;
            $rowsById[$rowId] = $row;
            $childrenByParent[$parentId ?? 0][] = $rowId;
        }

        $roots = collect($rowsById)
            ->keys()
            ->filter(function ($rowId) use ($rowsById) {
                $parentId = $rowsById[$rowId]->parent_id;

                return $parentId === null || !isset($rowsById[(int) $parentId]);
            })
            ->values()
            ->all();

        $walk = function (int $rowId, bool $parentCountryVisible, ?array $parentStorefronts) use (
            &$walk,
            &$visited,
            &$visibleIds,
            $rowsById,
            $childrenByParent,
            $country,
            $storefront,
            $defaultOnly,
            $defaultStorefront
        ): void {
            if (isset($visited[$rowId]) || !isset($rowsById[$rowId])) {
                return;
            }

            $visited[$rowId] = true;
            /** @var self $row */
            $row = $rowsById[$rowId];

            $countryVisible = $parentCountryVisible;
            if ($countryVisible && $country) {
                $countryVisible = $row->isAvailableForCountry($country, false);
            }

            $explicitStorefronts = $row->prepareStorefrontCodes($row->storefronts ?? []);
            if (!empty($explicitStorefronts)) {
                $allowedStorefronts = $explicitStorefronts;
            } elseif ($row->parent_id !== null) {
                $allowedStorefronts = $parentStorefronts;
            } elseif ($defaultOnly) {
                $allowedStorefronts = [$defaultStorefront];
            } else {
                $allowedStorefronts = null;
            }

            $storefrontVisible = true;
            if ($storefront && is_array($allowedStorefronts)) {
                $storefrontVisible = in_array($storefront, $allowedStorefronts, true);
            }

            if ($countryVisible && $storefrontVisible) {
                $visibleIds[] = $rowId;
            }

            foreach ($childrenByParent[$rowId] ?? [] as $childId) {
                $walk($childId, $countryVisible, $allowedStorefronts);
            }
        };

        foreach ($roots as $rootId) {
            $walk((int) $rootId, true, null);
        }

        foreach (array_keys($rowsById) as $rowId) {
            if (!isset($visited[$rowId])) {
                $walk((int) $rowId, true, null);
            }
        }

        return array_values(array_unique($visibleIds));
    }
    
    /**
     * getCategoryNodeIdList
     *
     * @param  mixed $slug
     * @param  mixed $id
     * @return void
     */
    public static function getCategoryNodeIdList(?string $slug = null, ?int $id = null, ?string $country = null) {
      $country = static::resolveCountry($country, true);

      $query = static::query();

      if($country) {
        $query->forCountry($country, false);
      }

      if($slug !== null) {
        $category = $query->where('slug', $slug)->first();
      }elseif($id !== null) {
        $category = $query->where('id', $id)->first();
      }else {
        $category = null;
      }

      if(!$category) {
        return null;
      }

      $node_ids = $category->collectNodeIds($country, false);

      return empty($node_ids)? null: $node_ids;
    }


    public function getExtrasTransDecodedAttribute() {
      if(empty($this->extras_trans)) return null;

      return json_decode($this->extras_trans, true);
    }
    
    /**
     * getAllParents
     *
     * @return void
     */
    public function getParentNode($category = null, $carry = null, ?string $country = null, bool $fallbackToStore = true) {
      $carry = $carry? $carry: collect();
			$category = $category? $category: $this;
      $country = static::resolveCountry($country, $fallbackToStore);

      if(!$country || $category->isAvailableForCountry($country, false)) {
        $carry->push($category);
      }

      if($category->parent) {
        return $this->getParentNode($category->parent, $carry, $country, false);
      }else {
        return $carry;
      }
    }

    /**
     * getAllParents
     *
     * @return void
     */
    public static function getParentNodeIds(?string $slug = null, ?int $id = null, ?string $country = null) {
      $country = static::resolveCountry($country, true);

      $query = static::query();
      if($country) {
        $query->forCountry($country, false);
      }

      if($slug !== null) {
        $category = $query->where('slug', $slug)->first();
      }elseif($id !== null) {
        $category = $query->where('id', $id)->first();
      }else {
        $category = null;
      }

      if(!$category) {
        return null;
      }

      $node_list = $category->getParentNode($category, null, $country);

      if(!$node_list) {
        return null;
      }

      $node_ids = $node_list
        ->filter(function ($node) use ($country) {
          return !$country || $node->isAvailableForCountry($country, false);
        })
        ->pluck('id')
        ->toArray();

      return empty($node_ids)? null: $node_ids;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function products()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Product', 'ak_category_product');
    }

    public function parent()
    {
      return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
      return $this->hasMany(self::class, 'parent_id');
    }
    
    public function attributes()
    {
        return $this->belongsToMany('Backpack\Store\app\Models\Attribute', 'ak_attribute_category');
    }

    /**
     * Google merchants category
     *
     * @return void
     */
    public function merchant()
    {
      return $this->belongsTo(MerchantCategory::class, 'merchant_id');
    }
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeNoEmpty($query){
      return $query->has('products');
    }

    public function scopeActive($query){
      return $query->where('is_active', 1);
    }

    public function scopeRoot($query){
      return $query->where('parent_id', NULL);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */ 

    public function getUniqStringAttribute(): string
    {
        return $this->formatUniqString([
            $this->uniqTitle,
            $this->slug,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            $this->getAdminCountriesLabel(),
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $headline = $this->formatUniqString([$this->uniqTitle]);

        return $this->formatUniqHtml($headline, [
            $this->slug,
            sprintf('status: %s', $this->is_active ? 'active' : 'hidden'),
            $this->getAdminCountriesLabel(),
        ]);
    }

    
    public function getUniqTitleAttribute() {
      $categories_array = $this->getParentNode($this);

      // Собираем имена категорий в массив
      $names = $categories_array->reverse()->pluck('name')->toArray();

      // Формируем строку цепочки
      $node = implode(' -> ', $names);

      return "id: {$this->id} ➡ {$node}";
    }

    public function getAdminCountriesLabel(): string
    {
      $list = $this->countriesList;

      if(!$list || empty($list)) {
        return 'Все';
      }

      return implode(', ', $list);
    }

    public function getAdminStoreOnlyCountriesLabel(): string
    {
        $list = $this->storeOnlyCountriesList;

        if (!$list || empty($list)) {
            return 'Нет';
        }

        return implode(', ', $list);
    }

    public function getAdminStorefrontsLabel(): string
    {
        $list = $this->storefrontsList;

        if (!$list || empty($list)) {
            return \Backpack\Store\app\Services\Store::applyUnassignedCategoriesToDefaultStorefront()
                ? 'Default'
                : 'Все';
        }

        return implode(', ', $list);
    }

    /**
     * getSeoToArrayAttribute
     *
     * @return void
     */
    public function getSeoToArrayAttribute() {
      return !empty($this->seo)? json_decode($this->seo, true): null;
    }
    
    /**
     * getExtrasToArrayAttribute
     *
     * @return void
     */
    public function getExtrasToArrayAttribute() {
      return !empty($this->extras)? json_decode($this->extras): null;
    }
    
    /**
     * getImageAttribute
     *
     * @return void
     */
    public function getImageAttribute()
    {
        return $this->getFirstImage();
    }
    
    /**
     * getImageSrcAttribute
     *
     * @return void
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
     * getNodeIdsAttribute
     *
     * @param  mixed $category
     * @return void
     */
    public function getNodeIdsAttribute($category){
			$category = $category? $category: $this;

      if(!$category instanceof self) {
        return [];
      }

      return $category->collectNodeIds(null, false);
    }
    

    /**
     * getRootCategory
     *
     * @return void
     */
    public function getRootCategoryAttribute() {
      $this_category = $this;
      
      while($this_category->parent) {
        $this_category = $this_category->parent;
      }

      return $this_category;
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SERVICE OPERATION
    |--------------------------------------------------------------------------
    */
    public function getServiceMergeConfiguration(): array
    {
        return [
            'label' => 'Слияние категорий',
            'description' => 'Объедините дубликаты категорий и перенесите связанные сущности.',
            'candidate_search' => ['name', 'slug', 'id'],
            'fields' => [
                'name' => [
                    'label' => 'Название',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'content' => [
                    'label' => 'Контент',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'seo' => [
                    'label' => 'SEO',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'extras_trans' => [
                    'label' => 'Доп. переводы',
                    'strategy' => 'translations',
                ],
                'extras' => [
                    'label' => 'Extras',
                    'strategy' => 'append',
                ],
                'params' => [
                    'label' => 'Параметры',
                    'strategy' => 'append',
                ],
                'countries' => [
                    'label' => 'Страны',
                    'strategy' => 'append',
                ],
                'storefronts' => [
                    'label' => 'Storefronts',
                    'strategy' => 'append',
                ],
                'images' => [
                    'label' => 'Изображения',
                    'strategy' => 'append',
                ],
            ],
            'relations' => [
                'products' => [
                    'label' => 'Назначенные товары (ak_category_product)',
                    'type' => 'table',
                    'table' => 'ak_category_product',
                    'column' => 'category_id',
                    'primary_key' => 'id',
                    'unique' => ['product_id'],
                    'default' => true,
                    'help' => 'Перепривязывает товары к базовой категории и убирает дубликаты записей.',
                ],
                'attributes' => [
                    'label' => 'Привязанные атрибуты',
                    'type' => 'table',
                    'table' => 'ak_attribute_category',
                    'column' => 'category_id',
                    'primary_key' => 'id',
                    'unique' => ['attribute_id'],
                ],
                'children' => [
                    'label' => 'Дочерние категории',
                    'type' => 'table',
                    'table' => 'ak_product_categories',
                    'column' => 'parent_id',
                    'primary_key' => 'id',
                    'help' => 'Устанавливает нового родителя для вложенных категорий.',
                ],
            ],
        ];
    }
}
