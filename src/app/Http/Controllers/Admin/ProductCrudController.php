<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Models\Category;
// use Aimix\Shop\app\Models\Brand;

// use Backpack\LangFileManager\app\Models\Language;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ProductCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    private $languages = ['ru'];
    
    private $categories;
    private $brands;
    private $categories_by_lang;
    private $current_category;
    private $current_language;
    
    public function setup()
    {
        $this->crud->setModel('Backpack\Store\app\Models\Product');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/product');
        $this->crud->setEntityNameStrings('товар', 'товары');
        
        if(config('aimix.shop.enable_brands')) {
          $this->brands = Brand::NoEmpty()->pluck('name', 'id')->toArray();
        }
        $this->current_category = \Request::input('category_id')? \Request::input('category_id') : null;

        // if(config('aimix.aimix.enable_languages')) {
        //   $this->languages = Language::getActiveLanguagesNames();

        //   $this->current_language = \Request::input('language_abbr')? \Request::input('language_abbr') : null;
        // }
          $this->crud->query = $this->crud->query->withoutGlobalScopes();
          
          $this->crud->model->clearGlobalScopes();
        
        $this->categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
        
        $this->crud->model->clearGlobalScopes();
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb();
        $this->crud->addFilter([
          'name' => 'category_id',
          'label' => 'Категория',
          'type' => 'select2',
        ], function(){
          return $this->categories;
        }, function($value){
          $this->crud->addClause('where', 'category_id', $value);
        });
        
      if(config('aimix.shop.enable_brands')) {
        $this->crud->addFilter([
          'name' => 'brand_id',
          'label' => 'Производитель',
          'type' => 'select2'
        ], function(){
          return $this->brands;
        }, function($value){
          $this->crud->addClause('where', 'brand_id', $value);
        });
      }
      
        if(config('aimix.aimix.enable_languages')) {
          $this->crud->addFilter([
            'name'  => 'language',
            'type'  => 'select2',
            'label' => 'Язык'
          ], function () {
            return $this->languages;
          }, function ($value) { // if the filter is active
            $this->crud->addClause('where', 'language_abbr', $value);
          });
          
          $this->crud->addColumn([
            'name' => 'language_abbr',
            'label' => 'Язык',
          ]);
        }
        
        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название'
        ]);
        
        $this->crud->addColumn([
          'name' => 'category_id',
          'label' => 'Категория',
          'type' => 'select',
          'entity' => 'category',
          'attribute' => 'name',
          'model' => 'Aimix\Shop\app\Models\Category',
          'options'   => (function ($query) {
              return $query->withoutGlobalScopes()->get();
          }),
        ]);
        
      if(config('aimix.shop.enable_brands')) {
        $this->crud->addColumn([
          'name' => 'brand_id',
          'label' => 'Производитель',
          'type' => 'select',
          'entity' => 'brand',
          'attribute' => 'name',
          'model' => 'Aimix\Shop\app\Models\Brand',
        ]);
      }

    if(config('aimix.shop.enable_product_rating')) {
      $this->crud->addColumn([
        'name' => 'rating',
        'label' => 'Рейтинг',
      ]);
    }
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProductRequest::class);
        $this->categories_by_lang = Category::withoutGlobalScopes();
        $language_in_url = (boolean) $this->current_language;

        if(\Route::current()->parameter('id'))
          $this->current_language = $this->crud->getEntry(\Route::current()->parameter('id'))->language_abbr;

        $this->current_language = \Request::input('language_abbr')? \Request::input('language_abbr') : $this->current_language;

        if(config('aimix.aimix.enable_languages')) {
          if($this->current_language) {
            $this->categories_by_lang = $this->categories_by_lang->where('language_abbr', $this->current_language);
          } else {
            $this->categories_by_lang = $this->categories_by_lang->where('language_abbr', array_key_first($this->languages));
          }
        }

        $this->categories_by_lang = $this->categories_by_lang->pluck('name', 'id')->toArray();

        $this->crud->attributes = Category::withoutGlobalScopes()->find(array_key_first($this->categories_by_lang))->attributes->keyBy('id');

        $this->crud->attributes = \Route::current()->parameter('id') && !$language_in_url? Category::withoutGlobalScopes()->find($this->crud->getEntry(\Route::current()->parameter('id'))->category_id)->attributes : $this->crud->attributes;

        if($this->current_category)
          $this->crud->attributes = Category::withoutGlobalScopes()->find($this->current_category)->attributes->keyBy('id');

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      if(config('aimix.aimix.enable_languages')) {
        $this->crud->addField([
          'name' => 'language_abbr',
          'label' => 'Язык',
          'type' => 'select2_from_array',
          'options' => $this->languages,
          'value' => $this->current_language ?? $this->current_language,
          'attributes' => [
            'onchange' => 'window.location.search += "&language_abbr=" + this.value'
          ]
        ]);
      }

        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
        ]);
        
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text'
        ]);
        
        $this->crud->addField([
          'name' => 'slug',
          'label' => 'URL',
          'prefix' => url('/products').'/',
          'hint' => 'По умолчанию будет сгенерирован из названия.'
        ]);
        
        $this->crud->addField([
          'name' => 'image',
          'label' => 'Изображение',
          'type' => 'browse',
          'hint' => 'Изображение, которое будет отображаться в каталоге'
        ]);
        
      if(config('aimix.shop.enable_multiple_product_images')) {
        $this->crud->addField([
          'name' => 'images',
          'label' => 'Изображения',
          'type' => 'browse_multiple',
        ]);
      }
        
        // $this->crud->addField([
        //   'name' => 'category_id',
        //   'label' => 'Категория',
        //   'type' => 'select2',
        //   'entity' => 'category',
        //   'attribute' => 'name',
        //   'model' => 'Aimix\Shop\app\Models\Category',
        //   'value' => $this->current_category,
        //   'attributes' => [
        //     'onchange' => 'window.location.search += "&category_id=" + this.value'
        //   ]
        // ]);

        $this->crud->addField([
          'name' => 'category_id',
          'label' => 'Категория',
          'type' => 'select2_from_array',
          'value' => $this->current_category ?? $this->current_category,
          'options' => $this->categories_by_lang,
          'attributes' => [
            'onchange' => 'window.location.search += "&category_id=" + this.value'
          ],
          
        ]);
        
      if(config('aimix.shop.enable_brands')) {
        $this->crud->addField([
          'name' => 'brand_id',
          'label' => 'Производитель',
          'type' => 'select2',
          'entity' => 'brand',
          'attribute' => 'name',
          'model' => 'Aimix\Shop\app\Models\Brand',
        ]);
      }
        
      if(config('aimix.shop.enable_is_hit')) {
        $this->crud->addField([
          'name' => 'is_hit',
          'label' => 'Хит',
          'type' => 'boolean',
        ]);
      }
        
      if(config('aimix.shop.enable_product_promotions')) {
        $this->crud->addField([
          'name' => 'sales',
          'label' => 'Акции',
          'fake' => true,
          'type' => 'table',
          'store_in' => 'extras',
          'entity_singular' => 'акцию',
          'columns' => [
            'discount' => 'Скидка, руб.',
            'desc' => 'Описание',
          ]
        ]);
      }
        
        $this->crud->addField([
          'name' => 'description',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 7
          ]
        ]);
        
        $this->crud->addField([
          'name' => 'mod',
          'label' => 'Модификации',
          'type' => 'modification',
        ]);
        
        
        // --  --
		$this->crud->addField([
		    'name' => 'meta_title',
		    'label' => "Meta Title", 
		    'type' => 'text',
		    'fake' => true, 
		    'store_in' => 'extras'
		]);
		
		$this->crud->addField([
		    'name' => 'meta_description',
		    'label' => "Meta Description", 
		    'type' => 'textarea',
		    'fake' => true, 
		    'store_in' => 'extras'
		]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();

        // $this->crud->attributes = $this->current_category? Category::withoutGlobalScopes()->find($this->current_category)->attributes: ($this->crud->getEntry(\Route::current()->parameter('id'))? $this->crud->getEntry(\Route::current()->parameter('id'))->category->attributes : null);
    }
}
