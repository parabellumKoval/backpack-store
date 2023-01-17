<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\Store\app\Models\Category;
// use Aimix\Shop\app\Models\Brand;

use Backpack\Store\app\Http\Controllers\Admin\Base\ProductCrudBase;

// use Backpack\LangFileManager\app\Models\Language;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ProductCrudController extends ProductCrudBase
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    
    private $categories;
    private $brands;
    
    public function setup()
    {
        $this->crud->setModel('Backpack\Store\app\Models\Product');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/product');
        $this->crud->setEntityNameStrings('товар', 'товары');
        
        if(config('backpack.store.enable_brands')) {
          $this->brands = Brand::NoEmpty()->pluck('name', 'id')->toArray();
        }

        // SET OPERATION
        $this->setOperation();

        // CURRENT MODEL
        $this->setEntry();
        
        // SET PARENT MODEL
        $this->setParentEntry();
          
        // SET CATEGORY MODEL
        $this->setCategories();

        // SET ATTRIBUTES MODEL 
        $this->setAttrs();

        // $this->crud->query = $this->crud->query->withoutGlobalScopes();
        
        // $this->crud->model->clearGlobalScopes();
        
        // $this->categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
        
        // $this->crud->model->clearGlobalScopes();
    }
    protected function fetchOrder()
    {
        return $this->fetch(\Backpack\Store\app\Models\Order::class);
    }

    protected function setupListOperation()
    {
        //remove product modifications from list view
        $this->crud->addClause('base');
        
        $this->crud->addFilter([
          'name' => 'category_id',
          'label' => 'Категория',
          'type' => 'select2',
        ], function(){
          return $this->categories;
        }, function($value){
          $this->crud->addClause('where', 'category_id', $value);
        });
        
        if(config('backpack.store.enable_brands')) {
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
        
        $this->crud->addColumn([
          'name' => 'imageSrc',
          'label' => 'Фото',
          'type' => 'image',
          'height' => '50px',
          'width'  => '50px',
        ]);

        $this->crud->addColumn([
          'name' => 'id',
          'label' => 'ID'
        ]);

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название'
        ]);
        
        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => 'Вкл',
          'type' => 'check'
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProductRequest::class);

        $this->crud->addField([
          'name' => 'parent_id',
          'type' => 'hidden',
          'value' => \Request::query('parent_id') ?? null
        ]);
        
        $this->crud->addField([
          'name' => 'modifications',
          'label' => 'Модификации',
          'type' => 'modification_switcher',
          'tab' => 'Основное'
        ]);


        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
          'tab' => 'Основное'
        ]);
        
        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
          'tab' => 'Основное'
        ]);

        // SHORT NAME FOR MODIFICATIONS
        if($this->entry && !$this->entry->isBase || \Request::get('parent_id')) {
          $this->crud->addField([
            'name' => 'short_name',
            'label' => 'Краткое название модификации',
            'type' => 'text',
            'tab' => 'Основное'
          ]);
        }
        
        // SLUG
        $this->crud->addField([
          'name' => 'slug',
          'label' => 'URL',
          'hint' => 'По умолчанию будет сгенерирован из названия.',
          'tab' => 'Основное'
        ]);
        
        // CATEGORY
        // $category_attributes = [
        //   'onchange' => "
        //     reload_page(event);

        //     function reload_page(event) {
        //       const value = event.target.value
        //       url = insertParam('category_id', value)
        //     };

        //     function insertParam(key, value) {
        //       key = encodeURIComponent(key);
        //       value = encodeURIComponent(value);
          
        //       // kvp looks like ['key1=value1', 'key2=value2', ...]
        //       var kvp = document.location.search.substr(1).split('&');
        //       let i=0;
          
        //       for(; i<kvp.length; i++){
        //           if (kvp[i].startsWith(key + '=')) {
        //               let pair = kvp[i].split('=');
        //               pair[1] = value;
        //               kvp[i] = pair.join('=');
        //               break;
        //           }
        //       }
          
        //       if(i >= kvp.length){
        //           kvp[kvp.length] = [key,value].join('=');
        //       }
          
        //       // can return this or...
        //       let params = kvp.join('&');
          
        //       // reload page with new params
        //       document.location.search = params;
        //   }
        //   "
        // ];

        // disable if product is not base but modification of other product
        if($this->entry && !$this->entry->isBase || \Request::get('parent_id')) {
          $category_attributes['disabled'] = 'disabled';
        } else {
          $category_attributes = [];
        }

        // $this->crud->addField([
        //   'name' => 'category_id',
        //   'label' => 'Категория',
        //   'type' => 'select2',
        //   'entity' => 'category',
        //   'attribute' => 'name',
        //   'model' => 'Backpack\Store\app\Models\Category',
        //   'tab' => 'Основное',
        //   'value' => \Request::get('category_id')? \Request::get('category_id'): Category::first()->id,
        //   'attributes' => $category_attributes
        // ]);



        $this->crud->addField([
          'name' => 'categories',
          'label' => 'Категории',
          'type' => 'select2_multiple',
          'entity' => 'categories',
          'attribute' => 'name',
          'model' => 'Backpack\Store\app\Models\Category',
          'tab' => 'Основное',
          'hint' => 'Характеристики товара зависят от выбранных категорий. После сохранения записи характеристики будут синхронизированы с категориями.',
          'value' => $this->categories? $this->categories: null,
          'attributes' => $category_attributes
        ]);

        // PRICE
        $this->crud->addField([
          'name' => 'price',
          'label' => 'Цена',
          'type' => 'number',
          'prefix' => '$',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
          'attributes' => [
            'step' => 0.01,
            'min' => 0
          ],
          'tab' => 'Основное'
        ]);

        // OLD PRICE
        $this->crud->addField([
          'name' => 'old_price',
          'label' => 'Старая цена',
          'type' => 'number',
          'prefix' => '$',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
          'attributes' => [
            'step' => 0.01,
            'min' => 0
          ],
          'tab' => 'Основное'
        ]);
        
        // DESCRIPTION
        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 7
          ],
          'tab' => 'Основное'
        ]);

        // CODE
        $this->crud->addField([
          'name' => 'code',
          'label' => 'Артикул',
          'tab' => 'Основное'
        ]);
        
        
        // IMAGES
        $this->crud->addField([
          'name'  => 'images',
          'label' => 'Изображения',
          'type'  => 'repeatable',
          'fields' => [
            [
              'name' => 'src',
              'label' => 'Изображение',
              'type' => 'browse'
            ],
            [
              'name' => 'alt',
              'label' => 'alt'
            ],
            [
              'name' => 'title',
              'label' => 'title'
            ],
            [
              'name' => 'size',
              'type' => 'radio',
              'label' => 'Размер',
              'options' => [
                'cover' => 'Cover',
                'contain' => 'Contain'
              ],
              'inline' => true
            ]
          ],
          'new_item_label'  => 'Добавить изобрежение',
          'init_rows' => 1,
          'tab' => 'Изображения'
        ]);
        
        
        // ATTRIBUTES
        $this->setAttributesFields();

        // META TITLE
        $this->crud->addField([
            'name' => 'meta_title',
            'label' => "Meta Title", 
            'type' => 'text',
            'fake' => true, 
            'store_in' => 'seo',
            'tab' => 'SEO'
        ]);
        
        // META DESCRIPTION
        $this->crud->addField([
            'name' => 'meta_description',
            'label' => "Meta Description", 
            'type' => 'textarea',
            'fake' => true, 
            'store_in' => 'seo',
            'tab' => 'SEO'
        ]);


        // if(method_exists($this, 'setupOrderFields'))
        //   $this->setupOrderFields();

        // if(method_exists($this, 'setupReviewFields'))
        //   $this->setupReviewFields();


        // parent::setupCreateOperation();
    }

    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();

        // $this->crud->attributes = $this->current_category? Category::withoutGlobalScopes()->find($this->current_category)->attributes: ($this->crud->getEntry(\Route::current()->parameter('id'))? $this->crud->getEntry(\Route::current()->parameter('id'))->category->attributes : null);
    }

    public function setAttributesFields() {
    
      if(config('backpack.store.enable_attributes', false) && isset($this->attrs) && $this->entry) {
        
        $this->crud->addField([
          'name' => 'props',
          'type' => 'hidden',
          'value' => null
        ]);


        $attr_fields = [];

        foreach($this->attrs as $index => $attribute) {
          $id = $attribute->id;
          $values = json_decode($attribute->values);

          if($this->entry && $this->entry->attrs) {
            $model_attribute = $this->entry->attrs()->find($attribute->id);
            $value = $model_attribute? $model_attribute->pivot->value: null;
          }else {
            $value = null;
          }
          
          $attr_fields[$index] = [
            'name' => "props[{$id}]",
            'label' => $attribute->name,
            'tab' => 'Характеристики'
          ];

          if($attribute->type === 'checkbox')
          {
            $value = json_decode($value);

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'select_from_array',
                'allows_multiple' => true,
                'options' => $values,
                'value' => $value,
              ]
            );
          }
          else if($attribute->type === 'radio')
          {
            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'select_from_array',
                'options' => $values,
                'value' => $value,
              ]
            );
          }
          else if($attribute->type === 'number')
          {
            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'number',
                'attributes' => [
                  'min' => $values->min,
                  'max' => $values->max,
                  'step' => $values->step,
                ],
                'value' => $value,
              ]
            );
          }
        }

        foreach($attr_fields as $attr_field) {
          $this->crud->addField($attr_field);
        }
      }
      else {
        $this->crud->addField([
          'name'  => 'no_attributes',
          'type'  => 'custom_html',
          'value' => "
          <p>Для редактирования характеристик сперва убедитесь, что:</p>
          <ul>
            <li>Выбрана категория записи</li>
            <li>Выбранной категории соответсвует хотябы один атрибут</li>
            <li>Данные были сохранены хотябы один раз</li>
          </ul>",
          'tab' => 'Характеристики'
        ]);
      }
    }

    private function setAttrs() {
      if(!in_array($this->opr, ['create', 'update']))
        return;

      $this->attrs = collect();

      if(!$this->categories || !$this->categories->count())
        return;
      
      foreach($this->categories as $category) {
        if($category->attributes)
          $this->attrs = $this->attrs->merge($category->attributes);
      }
    }

    private function setCategories()
    {
      if(!in_array($this->opr, ['create', 'update']))
        return;

      $query_category_ids = \Request::query('category_id');

      if($query_category_ids) {
        $this->categories = Category::whereIn('id', $query_category_ids)->get();
        return;
      }

      
      if(isset($this->parent_entry) && !empty($this->parent_entry))
      {
        $this->categories = $this->parent_entry->categories;
      }
      elseif($this->entry)
      {
        $this->categories = $this->entry->categories;
      }

    //   if($query_category_id)
    //   {
    //     $this->category = Category::find($query_category_id);
    //   }
    //   else if($this->opr === 'create') 
    //   {
    //     if(isset($this->parent_entry) && !empty($this->parent_entry)){
    //       $this->category = $this->parent_entry->category;
    //     } else {
    //       $this->category = null;
    //     }
    //   }
    //   else if($this->opr === 'update') 
    //   {
    //     $this->category = $this->entry->category;
    //   }
    //   else 
    //   {
    //     $this->category = null;
    //   }

    //   if(!$this->category)
    //   {
    //     $this->category = Category::first();
    //   }
    }

    private function setEntry() {
      if($this->crud->getCurrentOperation() === 'update')
        $this->entry = $this->crud->getEntry(\Route::current()->parameter('id'));
      else
        $this->entry = null;
    }

    private function setParentEntry() {
      if(!empty($parent_id = \Request::query('parent_id')))
        $this->parent_entry = $this->crud->getEntry($parent_id);
      elseif($this->entry && $this->entry->parent){
        $this->parent_entry = $this->entry->parent;
      }else{
        $this->parent_entry = null;
      }
    }

    private function setOperation() {
      $this->opr = $this->crud->getCurrentOperation();
    }
}
