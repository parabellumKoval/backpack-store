<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// TRANSLATIONS
use Backpack\CRUD\app\Models\Traits\SpatieTranslatable\HasTranslations;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;

use Backpack\Store\app\Models\Traits\SearchCatalogTrait;
use Backpack\Store\app\Services\Search\SearchConfigurableAbstract;

// Images
use ParabellumKoval\BackpackImages\Traits\HasImages;

use Backpack\Store\app\Models\Traits\HasModification;

class Catalog extends SearchConfigurableAbstract
{

    use HasTranslations;
    use SearchCatalogTrait;
    use HasModification;

    use HasImages;
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_catalog';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;
    protected $guarded = [];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    protected $casts = [
        'is_available' => 'boolean',
        'in_stock'     => 'integer',
        'price'        => 'decimal:2',
        'old_price'    => 'decimal:2',
        'name'         => 'array',
        'short_name'   => 'array',
        'excerpt'      => 'array',
        'images'       => 'array',
        'extras'       => 'array',
        'category_ids' => 'array',
        'rating'       => 'float',
        'reviews'      => 'integer',
        'ratings'      => 'integer',
    ];

    protected $translatable = ['name', 'short_name', 'excerpt', 'categoryNamesArray', 'content', 'merchant_content', 'seo', 'attrs'];

    const DEFAULT_BY = 'created_at';
    const DEFAULT_DIR = 'desc';
    
    /**
     * Method getSortingData
     *
     * @return void
     */
    public static function getSortingData() {
      return [
        [
          'by' => 'created_at',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.news_desc')
        ],[
          'by' => 'created_at',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.news_asc')
        ],[
          'by' => 'price',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.price_asc')
        ], [
          'by' => 'price',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.price_desc')
        ],[
          'by' => 'in_stock',
          'dir' => 'desc',
          'caption' => __('backpack-store::filter.sorting.in_stock_desc')
        ],[
          'by' => 'in_stock',
          'dir' => 'asc',
          'caption' => __('backpack-store::filter.sorting.in_stock_asc')
        ]
      ];
    }
    
    public function scopeAvailable($q) {
      return $q->where('is_available', 1);
    }

    /**
     * Method getSortingDataWithActive
     *
     * @param $by $by [explicite description]
     * @param $dir $dir [explicite description]
     *
     * @return void
     */
    public static function getSortingDataWithActive($by, $dir) {
      $by = $by? $by: self::DEFAULT_BY;
      $dir = $dir? $dir: self::DEFAULT_DIR;

      $options = self::getSortingData();
      $options_map = array_map(function($item) use($by, $dir) {
        $item['active'] = $item['by'] === $by && $item['dir'] === $dir? true: false;
        return $item;
      }, $options);

      return $options_map;
    }

    public static function getCacheCases() {
      $config = \Settings::get('dress.store.cache.cases');
      return $config;
    }

   
    /** Отношение (если вызвать КАК МЕТОД — будет SQL). Оставляем для совместимости. */
    public function modifications(): HasMany
    {
        return $this->hasMany(self::class, 'group_id', 'group_id')
            ->where('country_code', $this->country_code)
            ->where('is_available', 1);
    }

    /** Коллекция модификаций БЕЗ SQL — только если их пришили через setRelation. */
    public function getModificationsLoadedAttribute(): ?\Illuminate\Support\Collection
    {
        if ($this->relationLoaded('modifications')) {
          return $this->getRelation('modifications');
        }else {
          return $this->modifications;
        }

        return null;
    }

    /**
     * Готовая ресурсная коллекция модификаций БЕЗ SQL.
     * Класс ресурса берём из конфига: backpack.store.resources.product.tiny
     * Если не задан — вернём null (чтобы не трогать БД).
     */
    public function getResourceModificationsAttribute()
    {
        $mods = $this->modifications_loaded;
        if (!$mods || $mods->isEmpty()) {
            return null;
        }

        return $this->buildResourceModifications($mods);
    }

    public function getActiveModificationAttribute()
    {
        $mod = $this->relationLoaded('active_modification')? $this->getRelation('active_modification'): null;
        return $mod;
    }

    /** Бренд */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
    }

    /**
     * Категории по JSON-списку id.
     * Это НЕ стандартное Eloquent-отношение (нет pivot), но ресурсы часто просто читают коллекцию.
     */
    public function categories()
    {
        $ids = (array) ($this->category_ids ?? []);
        if (empty($ids)) return collect();
        return Category::query()->whereIn('id', $ids)->get();
    }

    protected function brandName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->brand->name ?? null,
            set: fn (string $value) => $value,
        );
    }

    // protected function getCategoryNamesArrayAttribute() {
    //   return $this->categories()->pluck('name')->toArray();
    // }


    /** Удобный алиас: главное изображение */
    // public function getImageAttribute(): ?array
    // {
    //     return $this->images[0] ?? null;
    // }


    public static function imageProviderName(?string $attribute = null): string
    {
        return 'local';
    }

    public static function imageStorageFolder(?string $attribute = null): string
    {
        return 'products';
    }

    /** Явный флаг "эта модификация прошла фильтр" — ставим из сервиса как атрибут */
    public function getPassedFilterAttribute(): bool
    {
        // если не проставлен — считаем false
        return (bool) ($this->attributes['passed_filter'] ?? false);
    } 

    public function getCurrencyAttribute() {
      return $this->currency_code;
    }

}
