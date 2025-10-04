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

      $html = "<div>" . $this->simpleCode . "</div>";


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
}


class FakeRelation {
  public function sync($value){}

  public function getRelated() {
    return new AttributeValue;
  }
}