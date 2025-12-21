<?php

namespace Backpack\Store\app\Models\Admin;

use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;

use Backpack\Store\app\Models\Product as BaseProduct;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Attribute;


class Product extends BaseProduct
{
    public $props = null;
    public $modificationsToSave = [];
    public $suppliers_data = null;
    public $default_supplier = null;

    public $available_languages = [];

    protected ?array $adminSupplierMatrixCache = null;
    protected ?array $adminSupplierWarehouseGridCache = null;
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

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
      
      $langs = config('backpack.crud.locales');
      $this->available_languages = array_keys($langs);
    }
    
    /**
     * getCategoriesString
     *
     * @return void
     */
    public function getCategoriesString() {
      if(!$this->categories || !$this->categories->count())
        return '-';
        
      $cat_links = $this->categories->map(function($item) {
        $short_name = mb_substr($item->name, 0, 15);
        return "<a href='/admin/product?category={$item->id}'>{$short_name}</a>";
      });

      return implode(', ', $cat_links->toArray());
    }

       
    public function getMorphClass()
    {
        return 'Backpack\Store\app\Models\Product';
    } 

    /**
     * Build supplier summary for admin tables (prices, stock, codes)
     */
    public function adminSupplierMatrix(): array
    {
        if ($this->adminSupplierMatrixCache !== null) {
            return $this->adminSupplierMatrixCache;
        }

        $this->loadMissing([
            'suppliers',
            'children.suppliers',
        ]);

        $children = $this->children instanceof \Illuminate\Support\Collection
            ? $this->children
            : collect($this->children ?? []);

        $childrenWithSuppliers = $children->filter(function ($child) {
            return $child->suppliers && $child->suppliers->count();
        });

        $shouldAggregateChildren = ($this->parent_id === null) && $childrenWithSuppliers->isNotEmpty();
        $products = $shouldAggregateChildren ? $childrenWithSuppliers : collect([$this]);

        $grouped = [];

        foreach ($products as $product) {
            $productSuppliers = $product->suppliers ?? collect();

            foreach ($productSuppliers as $supplier) {
                $supplierId = $supplier->id ?? null;

                if ($supplierId === null) {
                    continue;
                }

                if (!isset($grouped[$supplierId])) {
                    $grouped[$supplierId] = [
                        'supplier' => $supplier,
                        'prices' => [],
                        'stocks' => [],
                        'codes' => [],
                    ];
                }

                $pivot = $supplier->pivot;

                if (!$pivot) {
                    continue;
                }

                if ($pivot->price !== null) {
                    $grouped[$supplierId]['prices'][] = (float) $pivot->price;
                }

                if ($pivot->in_stock !== null) {
                    $grouped[$supplierId]['stocks'][] = (float) $pivot->in_stock;
                }

                $code = trim((string) ($pivot->code ?? ''));
                $barcode = trim((string) ($pivot->barcode ?? ''));
                $value = $code !== '' ? $code : $barcode;

                if ($value !== '') {
                    $grouped[$supplierId]['codes'][] = $value;
                }
            }
        }

        $suppliers = collect($grouped)
            ->sortBy(function ($item) {
                $name = $item['supplier']->name ?? '';
                return mb_strtolower($name, 'UTF-8');
            })
            ->map(function ($item) {
                return [
                    'supplier' => $item['supplier'],
                    'price' => $this->summarizeNumericMetric($item['prices']),
                    'stock' => $this->summarizeNumericMetric($item['stocks']),
                    'codes' => collect($item['codes'])->unique()->values()->all(),
                ];
            })
            ->values()
            ->all();

        return $this->adminSupplierMatrixCache = [
            'is_composite' => $shouldAggregateChildren,
            'suppliers' => $suppliers,
        ];
    }

    public function adminSupplierWarehouseGrid(): array
    {
        if ($this->adminSupplierWarehouseGridCache !== null) {
            return $this->adminSupplierWarehouseGridCache;
        }

        if ($this->parent_id !== null) {
            $this->loadMissing([
                'parent.children.suppliers',
                'parent.suppliers',
            ]);
        }

        $rootProduct = $this->parent_id !== null
            ? ($this->relationLoaded('parent') ? $this->getRelation('parent') : $this->parent)
            : $this;

        if (!$rootProduct) {
            $rootProduct = $this;
        }

        $rootProduct->loadMissing([
            'children.suppliers',
            'suppliers',
        ]);

        $modifications = $rootProduct->children instanceof \Illuminate\Support\Collection
            ? $rootProduct->children
            : collect($rootProduct->children ?? []);

        $hasRealModifications = $modifications->isNotEmpty();

        if (!$hasRealModifications) {
            $modifications = collect([$rootProduct]);
        }

        $modifications = $modifications
            ->sortBy(function ($product) {
                $label = trim((string) ($product->short_name ?? $product->name ?? ''));

                if ($label === '') {
                    $label = (string) $product->id;
                }

                return mb_strtolower($label, 'UTF-8');
            })
            ->values();

        $modificationsData = $modifications
            ->map(function ($product) use ($rootProduct) {
                return [
                    'id' => $product->id,
                    'short_name' => $product->short_name,
                    'short_name_translations' => $product->getTranslations('short_name'),
                    'name' => $product->name,
                    'name_translations' => $product->getTranslations('name'),
                    'code' => $product->code,
                    'is_active' => (bool) $product->is_active,
                    'is_base' => $product->id === $rootProduct->id,
                ];
            })
            ->all();

        $suppliersMap = [];

        foreach ($modifications as $product) {
            $productSuppliers = $product->suppliers instanceof \Illuminate\Support\Collection
                ? $product->suppliers
                : collect($product->suppliers ?? []);

            foreach ($productSuppliers as $supplier) {
                $supplierId = $supplier->id;

                if ($supplierId === null) {
                    continue;
                }

                $supplierActive = (int) ($supplier->is_active ?? 1) !== 0;

                if (!$supplierActive) {
                    continue;
                }

                $pivot = $supplier->pivot;

                if (!$pivot) {
                    continue;
                }

                if ($pivot->is_active !== null && (int) $pivot->is_active === 0) {
                    continue;
                }

                if (!isset($suppliersMap[$supplierId])) {
                    $suppliersMap[$supplierId] = [
                        'id' => $supplierId,
                        'name' => $supplier->name ?? '—',
                        'color' => $supplier->color ?? '#d1d5db',
                        'currency' => $supplier->currency ?? $supplier->currency_code ?? '',
                        'items' => [],
                    ];
                }

                $suppliersMap[$supplierId]['items'][$product->id] = [
                    'code' => $pivot->code ?? null,
                    'barcode' => $pivot->barcode ?? null,
                    'in_stock' => $pivot->in_stock,
                    'price' => $pivot->price,
                    'old_price' => $pivot->old_price,
                ];
            }
        }

        $suppliers = collect($suppliersMap)
            ->sortBy(function ($supplier) {
                return mb_strtolower($supplier['name'] ?? '', 'UTF-8');
            })
            ->values()
            ->all();

        return $this->adminSupplierWarehouseGridCache = [
            'modifications' => $modificationsData,
            'suppliers' => $suppliers,
            'has_real_modifications' => $hasRealModifications,
            'root_product_id' => $rootProduct->id,
            'current_product_id' => $this->id,
        ];
    }

    protected function summarizeNumericMetric(array $values): array
    {
        if (!$values) {
            return [
                'has_data' => false,
                'min' => null,
                'max' => null,
            ];
        }

        return [
            'has_data' => true,
            'min' => min($values),
            'max' => max($values),
        ];
    }

    /**
     * Return images for list columns.
     * For modifications without own images use parent's images.
     */
    public function getImageCollectionPaths(string $attribute, ?int $limit = null): array
    {
        $paths = parent::getImageCollectionPaths($attribute, $limit);

        $hasOwnImages = collect($paths)
            ->filter(fn ($path) => $path !== null && $path !== '')
            ->isNotEmpty();

        if ($hasOwnImages || $this->parent_id === null) {
            return $paths;
        }

        $parent = $this->relationLoaded('parent') ? $this->getRelation('parent') : $this->parent;

        if (!$parent) {
            return $paths;
        }

        return $parent->getImageCollectionPaths($attribute, $limit);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function avsFake($value = null) {
      return new FakeRelation;
    }

    public function props($value = null) {
      return new FakeRelation;
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */


    
    /**
     * scopeFillQuality20
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeFillQualityLow($query) {
      $langs_list = $this->langs_list;
      return $query
              // Has not images
              // ->where(function($query) {
              //   $query->whereRaw('JSON_LENGTH(images) = ?', 0)
              //         ->orWhere('images', null);
              // })
              // Has not any content translation
              ->where(function($query) use($langs_list) {
                foreach($langs_list as $lang_key) {
                  $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) < ? ', 150);
                }

                $query->orWhere('content', null);
              });
              // Has not categories
              // ->has('categories', '=', 0)
              // Has not brand
              // ->has('brand', '=', 0)
              // Has no attributes
              // ->has('ap', '=', 0);
    }
    
    /**
     * scopeFillQuality40
     *
     * @param  mixed $query
     * @return void
     */
    public function scopeFillQualityNormal($query) {
      $langs_list = $this->langs_list;
      return $query
            // Has one at least one image
            // ->whereRaw('JSON_LENGTH(images) >= ?', 1)
            // has at least one translation 
            ->where(function($query) use($langs_list) {
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index? 'orWhereRaw': 'whereRaw';
                $query->{$function_name}('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) >= ? ', 150);
              }
            });
            // has category
            // ->has('categories', '>=', 1)
            // has brand
            // ->has('brand', '>=', 1)
            // has few attributes
            // ->has('ap', '<=', 3);
    }
    
    /**
     * scopeFillQuality100
     *
     * @param  mixed $query
     * @return void
     */
    public function scopefillQualityHight($query) {
      $langs_list = $this->langs_list;
      return $query
              // ->whereRaw('JSON_LENGTH(images) >= ?', 1)
              // has category
              // ->has('categories', '>=', 1)
              // has brand
              // ->has('brand', '>=', 1)
              // Name with all translations
              // ->where(function($query) use($langs_list) {
              //   foreach($langs_list as $lang_key) {
              //     $query->whereRaw('LENGTH(JSON_EXTRACT(name, "$.' . $lang_key . '")) >= ? ', 2);
              //   }
              // })
              // Content with all translations
              ->where(function($query) use($langs_list) {
                foreach($langs_list as $lang_key) {
                  $query->whereRaw('LENGTH(JSON_EXTRACT(content, "$.' . $lang_key . '")) >= ? ', 150);
                }
              });
              // has attributes
              // ->has('ap', '>=', 1);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    public function getAdminPriceAttribute() {
      return view('crud::columns.price', [
        'price' => $this->price,
        'currency' => store_currency_label($this->currency)
      ]);
    }

    public function getFillAdminAttribute() {
      $data = $this->extras['fill_quality'] ?? [];
      return view('store-crud::columns.product_quality', $data);
    }

    public function getFillQualityAttribute() {
      return app(\Backpack\Store\app\Services\ProductQualityService::class)->calculate($this);
    }

    /**
     * getAdminTranslationsAttribute
     *
     * @return void
     */
    public function getAdminTranslationsAttribute(){

      $translations = [];

      foreach($this->available_languages as $lang) {
        $content = $this->getTranslation('content', $lang, false);

        if(mb_strlen($content) > 150) {

          switch($lang) {
            case 'ru':
              $color = 'red';
              break;
            case 'uk':
              $color = 'blue';
              break;
            default:
              $color = 'black';
          }

          $translations[] = '<div><b style="color: ' . $color . '">' . mb_strtoupper($lang) . '</b></div>';
        }
      }

      $html = implode('', $translations);

      return $html;
    }
    
    /**
     * getAdminCodeAttribute
     *
     * @return void
     */
    public function getAdminCodeAttribute() {
      $supplier = $this->currentSp->supplier ?? null;

      $is_static_code = !empty($this->code)? true: false;

      $sp_count = $this->sp->count();

      if(!$this->sp->count()) {
        $total_sp = 0;
      }else {
        $total_sp = $is_static_code? $this->sp->count(): $this->sp->count() - 1;
      }

      $html = "<div>" . $this->code . "</div>";


      if($is_static_code) {
        $html .= "<b style='color: grey;'>САЙТ</b>";

        if($total_sp) {
          $html .= "<b style='font-size: 12px;' title='Всего поставщиков: " . $sp_count . "'> (🚚 " . $total_sp . ")</b>";
        }
      }else if($supplier) {
        $html .= "<b style='color: " . $supplier->color . ";'>" . $supplier->name . "</b>";

        if($total_sp) {
          $html .= "<b style='font-size: 12px;' title='Всего поставщиков: " . $sp_count . "'> (🚚 +" . $total_sp . ")</b>";
        }
      }

      return $html;
    }

    
    /**
     * getPropsAttribute
     *
     * @return void
     */
    public function getPropsAttribute() {
      // $attributes = $this->attrs;
      // $props = [];
      
      // foreach($attributes as $attribute){
      //   $values = json_decode($attribute->values);
      //   $props[$attribute->id] = $values[$attribute->pivot->value];
      // }

      // return $props;
    }
    
    /**
     * getInStockTotalSuppliersAttribute
     *
     * @return void
     */
    public function getInStockTotalSuppliersAttribute() {
      return $this->sp_sum_in_stock ?? 0;
    }
    
    /**
     * getSuppliersDataAttribute
     *
     * @return void
     */
    public function getSuppliersDataAttribute() {
      $suppliers = $this->suppliers;
      
      $data_array = [];
      foreach($suppliers as $supplier) {
        $data_array[] = [
          'supplier' => $supplier->id,
          'code' => $supplier->pivot->code,
          'barcode' => $supplier->pivot->barcode,
          'in_stock' => $supplier->pivot->in_stock,
          'is_active' => $supplier->pivot->is_active,
          'price' => $supplier->pivot->price,
          'old_price' => $supplier->pivot->old_price,
          'currency' => $supplier->currency,
          'countries' => $supplier->countries,
          'updated_at' => $supplier->pivot->updated_at->format('Y-m-d @ H:i:s'),
        ];
      }

      return $data_array;
    }
    
    /**
     * getDefaultSupplierAttribute
     *
     * @return void
     */
    public function getDefaultSupplierAttribute() {
      return $this->currentSp->toArray();
    }


    
    public function getPriceOverridesAttribute()
    {
        return $this->countryOverrides()
            ->get()
            ->map(function ($override) {
                return [
                    'country'    => $override->country_code,
                    'currency'   => $override->currency_code,
                    'price'      => $override->price_override,
                    'old_price'  => $override->old_price_override,
                ];
            })
            ->toArray();
    }
    


    public function getCategoryLinksAdminAttribute() {
      if(!$this->categories || !$this->categories->count())
        return '-';
        
      $cat_links = $this->categories->map(function($item) {
        return "<a href='/admin/product?category={$item->id}'>{$item->name}</a>";
      });

      return implode(', ', $cat_links->toArray());
    }


    public function getBrandLinkAdminAttribute() {
      if(!$this->brand)
        return '-';
        
        return "<a href='/admin/product?brand={$this->brand->id}'>{$this->brand->name}</a>";
    }

    public function getAdminNameAttribute() {
        return view('store-crud::columns.product_name', [
            // product
            'entry' => $this,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'brandLinkAdmin' => $this->brandLinkAdmin,
            'categoryLinksAdmin' => $this->categoryLinksAdmin,
            'modifications' => \Store::isModVertical()? $this->children->map(function($item) {
              return [
                'id' => $item->id,
                'name' => $item->short_name
              ];
            }): null
        ])->render();
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
    

    public function setPriceOverridesAttribute($value)
    {
        // Нормализуем вход
        $value = is_array($value) ? $value : [];

        // Очищаем пустые строки (без country)
        $value = array_filter($value, function ($row) {
            return !empty($row['country']);
        });

        // Преобразуем в формат для вставки
        $data = collect($value)->map(function ($row) {
            return [
                'country_code'        => $row['country'],
                'currency_code'       => $row['currency'] ?? null,
                'price_override'      => $row['price'] ?? null,
                'old_price_override'  => $row['old_price'] ?? null,
            ];
        });

        // Синхронизируем через отношение
        $this->countryOverrides()->delete(); // проще всего очистить и вставить
        if ($data->isNotEmpty()) {
            $this->countryOverrides()->createMany($data);
        }
    }

    /**
     * setPropsAttribute
     *
     * @param  mixed $attributes
     * @return void
     */
    public function setPropsAttribute($attributes) {
      //$this->attrs()->detach();
      if(!$attributes)
        return;

      
      foreach($attributes as $attr_key => $value) {
        $clear_value = is_array($value)? array_filter($value, fn($i) => $i !== null): trim($value);
        // $serialized_value = is_array($clear_value)? json_encode(array_values($clear_value)): $clear_value;
        
        $this->props[$attr_key] = $clear_value;

        // dd($clear_value);
        //$this->attrs()->attach($attr_key, ['value' => $serialized_value]);

        //syncWithoutDetaching
        // $this->attrs()->syncWithoutDetaching([
        //   $attr_key => ['value' => $serialized_value]
        // ]);

        // $this->ap()->
      }
    }
    
    /**
     * setModificationsAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setModificationsAttribute($value) {
      $this->modificationsToSave = $value;
    }

    /**
     * setSuppliersAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setSuppliersDataAttribute($value) {
      // $this->suppliers_data = json_decode($value, true);
      $this->suppliers_data = $value;
    }
    
    /**
     * setSupplierAttribute
     *
     * @param  mixed $value
     * @return void
     */
    public function setDefaultSupplierVirtualAttribute($value) {
      $this->default_supplier = Request::input('defaultSupplier', []);
    }


    public function setDisabledRegionsAttribute($value) {
    }


    /*
    |--------------------------------------------------------------------------
    | SERVICE OPERATION
    |--------------------------------------------------------------------------
    */
    public function getServiceMergeConfiguration(): array
    {
        return [
            'label' => 'Слияние товаров',
            'description' => 'Объединяет дубликаты товаров и переносит связанные данные.',
            'cards' => [
                'entry' => 'store-crud::service.cards.product-entry',
                'result' => 'store-crud::service.cards.product',
            ],
            'candidate_search' => ['name', 'slug', 'code', 'id'],
            'fields' => [
                'name' => [
                    'label' => 'Название',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'short_name' => [
                    'label' => 'Короткое название',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'content' => [
                    'label' => 'Описание',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'merchant_content' => [
                    'label' => 'Контент для маркетплейсов',
                    'strategy' => 'translations',
                    'default' => true,
                ],
                'excerpt' => [
                    'label' => 'Краткое описание',
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
                'images' => [
                    'label' => 'Изображения',
                    'strategy' => 'append',
                ],
                'props' => [
                    'label' => 'Характеристики',
                    'strategy' => 'append',
                ],
                'priceOverrides' => [
                    'label' => 'Переопределения цен',
                    'strategy' => 'append',
                    'handler' => 'mergePriceOverridesField',
                ],
                // 'suppliersData' => [
                //     'label' => 'Данные поставщиков',
                //     'strategy' => 'append',
                //     'default' => true,
                //     'handler' => 'mergeSuppliersDataField',
                // ],
            ],
            'relations' => [
                'categories' => [
                    'label' => 'Категории',
                    'type' => 'table',
                    'table' => 'ak_category_product',
                    'column' => 'product_id',
                    'primary_key' => 'id',
                    'unique' => ['category_id'],
                    'default' => true,
                    'help' => 'Переносит записи из pivot-таблицы и удаляет дубликаты.',
                ],
                'attributes' => [
                    'label' => 'Атрибуты (ak_attribute_product)',
                    'type' => 'table',
                    'table' => 'ak_attribute_product',
                    'column' => 'product_id',
                    'primary_key' => 'id',
                    'default' => true,
                    'unique' => ['attribute_id', 'attribute_value_id'],
                ],
                'suppliers' => [
                    'label' => 'Поставщики',
                    'type' => 'table',
                    'table' => 'ak_supplier_product',
                    'column' => 'product_id',
                    'primary_key' => 'id',
                    'default' => true,
                    'unique' => ['supplier_id'],
                ],
                'regional_contents' => [
                    'label' => 'Региональный контент',
                    'type' => 'table',
                    'table' => 'ak_product_regional_contents',
                    'column' => 'product_id',
                    'primary_key' => 'id',
                    'default' => true,
                    'unique' => ['country_code'],
                    'handler' => 'mergeRegionalContents',
                ],
                'taggables' => [
                    'label' => 'Теги',
                    'type' => 'table',
                    'table' => 'ak_taggables',
                    'column' => 'taggable_id',
                    'primary_key' => 'id',
                    'default' => true,
                    'unique' => ['tag_id'],
                    'constraints' => [
                        ['column' => 'taggable_type', 'value' => static::class],
                    ],
                ],
                'children' => [
                    'label' => 'Модификации',
                    'type' => 'table',
                    'table' => 'ak_products',
                    'column' => 'parent_id',
                    'primary_key' => 'id',
                    'default' => true,
                    'help' => 'Привязывает дочерние товары к новой базовой записи.',
                    'merge' => [
                        'label' => 'Сшивать модификации между собой',
                        'default' => true,
                        'default_mode' => 'short_name_similarity',
                        'modes' => [
                            'short_name_similarity' => [
                                'label' => 'Похожие короткие названия',
                                'description' => 'Игнорирует регистр, пробелы и знаки в short_name, при необходимости сравнивает по цифрам.',
                                'matcher' => 'normalized_attribute',
                                'attribute' => 'short_name',
                            ],
                        ],
                    ],
                ],
            ],
            'similar' => [
                'enabled' => true,
                'label' => 'Поиск похожих товаров',
                'description' => 'Использует Meilisearch, чтобы найти потенциальные дубликаты по имени и короткому названию.',
                'limit' => 20,
                'fields' => [
                    'name',
                    'short_name',
                ],
                'provider' => \Backpack\Store\app\Library\ServiceOperation\Similar\Providers\ProductMeilisearchProvider::class,
                'provider_options' => [
                    'countries' => array_keys(\Store::countries()),
                    'index_base' => 'products',
                    'locale_map' => \Store::defaultCountryLocales(),
                ],
                'exclude_children' => [
                    'enabled' => true,
                    'default' => true,
                    'relation' => 'children',
                    'column' => 'parent_id',
                    'key' => 'id',
                ],
                'card_view' => 'store-crud::service.cards.product',
            ],
        ];
    }

    /**
     * Merge supplier data while respecting the force flag and supplier ids.
     */
    public function mergeSuppliersDataField(self $source, array $payload): void
    {
        $field = $payload['field'] ?? 'suppliersData';
        $force = (bool) ($payload['force'] ?? false);

        $this->performArrayFieldMerge(
            $source,
            $field,
            'supplier',
            $force,
            function (array $item) {
                if (array_key_exists('supplier', $item) && $item['supplier'] !== null && $item['supplier'] !== '') {
                    $item['supplier'] = (int) $item['supplier'];
                }

                return $item;
            }
        );
    }

    /**
     * Merge price overrides grouped by country codes.
     */
    public function mergePriceOverridesField(self $source, array $payload): void
    {
        $field = $payload['field'] ?? 'priceOverrides';
        $force = (bool) ($payload['force'] ?? false);

        $this->performArrayFieldMerge(
            $source,
            $field,
            'country',
            $force,
            function (array $item) {
                if (isset($item['country'])) {
                    $country = strtolower(trim((string) $item['country']));
                    $item['country'] = $country !== '' ? $country : null;
                }

                return $item;
            }
        );
    }

    public function mergeRegionalContents(self $source, array $payload = []): void
    {
        $force = (bool) ($payload['force'] ?? false);

        $this->loadMissing('regionalContents');
        $source->loadMissing('regionalContents');

        $target = $this->relationLoaded('regionalContents') ? $this->regionalContents : collect();
        $sourceItems = $source->relationLoaded('regionalContents') ? $source->regionalContents : collect();

        $targetByCountry = $target instanceof \Illuminate\Support\Collection
            ? $target->keyBy(function ($item) {
                return strtolower((string) ($item->country_code ?? ''));
            })
            : collect();

        foreach ($sourceItems as $item) {
            $country = strtolower((string) ($item->country_code ?? ''));

            if ($country === '') {
                continue;
            }

            if ($targetByCountry->has($country) && !$force) {
                continue;
            }

            $this->regionalContents()->updateOrCreate(
                ['country_code' => $country],
                [
                    'content' => $item->getTranslations('content') ?: null,
                    'excerpt' => $item->getTranslations('excerpt') ?: null,
                    'merchant_content' => $item->getTranslations('merchant_content') ?: null,
                ]
            );
        }
    }

    /**
     * Merge a repeatable array field using a specific key column.
     *
     * @param  callable|null  $normalizer
     */
    public function performArrayFieldMerge(self $source, string $field, string $keyName, bool $force, ?callable $normalizer = null): void
    {
        $targetItems = $this->normalizeMergeableFieldValue($this->getAttribute($field));
        $sourceItems = $this->normalizeMergeableFieldValue($source->getAttribute($field));

        if ($sourceItems === []) {
            return;
        }

        if ($normalizer) {
            $targetItems = $this->applyMergeItemNormalizer($targetItems, $normalizer);
            $sourceItems = $this->applyMergeItemNormalizer($sourceItems, $normalizer);
        }

        $merged = $this->mergeItemsByKey($targetItems, $sourceItems, $keyName, $force);
        $this->setAttribute($field, $merged);
    }

    /**
     * Normalize mixed values into an array of associative rows.
     *
     * @param  mixed  $value
     * @return array<int, array<string, mixed>>
     */
    public function normalizeMergeableFieldValue($value): array
    {
        if ($value instanceof \Illuminate\Contracts\Support\Arrayable) {
            $value = $value->toArray();
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * Apply a normalizer callback to each row of data.
     *
     * @param  callable  $normalizer
     * @return array<int, array<string, mixed>>
     */
    public function applyMergeItemNormalizer(array $items, callable $normalizer): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $item = $normalizer($item);

            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    /**
     * Merge rows by a specific key with optional forced overwrites.
     *
     * @param  array<int, array<string, mixed>>  $targetItems
     * @param  array<int, array<string, mixed>>  $sourceItems
     * @return array<int, array<string, mixed>>
     */
    public function mergeItemsByKey(array $targetItems, array $sourceItems, string $keyName, bool $force): array
    {
        if ($sourceItems === []) {
            return $targetItems;
        }

        $result = array_values($targetItems);
        $positions = [];

        foreach ($result as $index => $item) {
            $key = $this->extractMergeKeyValue($item, $keyName);

            if ($key !== null && ! array_key_exists($key, $positions)) {
                $positions[$key] = $index;
            }
        }

        foreach ($sourceItems as $item) {
            $key = $this->extractMergeKeyValue($item, $keyName);

            if ($key === null) {
                if ($force) {
                    $result[] = $item;
                }

                continue;
            }

            if (! array_key_exists($key, $positions)) {
                $positions[$key] = count($result);
                $result[] = $item;

                continue;
            }

            if ($force) {
                $result[$positions[$key]] = $item;
            }
        }

        return array_values($result);
    }

    /**
     * Extract comparable key value from a row.
     */
    public function extractMergeKeyValue(array $item, string $keyName): ?string
    {
        if (! array_key_exists($keyName, $item)) {
            return null;
        }

        $value = $item[$keyName];

        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}


class FakeRelation {
  public function sync($value){}

  public function getRelated() {
    return new AttributeValue;
  }
}
