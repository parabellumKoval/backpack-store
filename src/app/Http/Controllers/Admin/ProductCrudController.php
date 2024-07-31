<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\ProductRequest;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Illuminate\Database\Eloquent\Builder;

// MODELS
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\AttributeValue;

//EVENTS
use Backpack\Store\app\Events\ProductSaved;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ProductCrudController extends CrudController
{
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    

    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \App\Http\Controllers\Admin\Traits\ProductCrud;
    
    private $categories;
    private $filter_categories;
    private $brands;
    private $attrs;
    
    private $product_class = null;

    public function setup()
    {
      $this->product_class = config('backpack.store.product.class_admin', 'Backpack\Store\app\Models\Admin\Product');

      $this->crud->setModel($this->product_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/product');
      $this->crud->setEntityNameStrings('товар', 'товары');


      // SET LOCALE
      $this->setLocale();

      // SET OPERATION
      $this->setOperation();

      // CURRENT MODEL
      $this->setEntry();
      
      // SET PARENT MODEL
      $this->setParentEntry();
        
      // SET CATEGORY MODEL
      $this->setCategories();

      // SET ATTRIBUTES MODEL 
      $this->setAttrsForCategories();

      // $this->crud->query = $this->crud->query->withoutGlobalScopes();
      
      // $this->crud->model->clearGlobalScopes();
      
      $this->filter_categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
      
      // if(config('backpack.store.brands.enable')) {
      //   $this->brands = Brand::NoEmpty()->pluck('name', 'id')->toArray();
      // }

      // $this->crud->model->clearGlobalScopes();

      // Set event listiner to Model
      $this->product_class::saved(function($entry) {
        // Attach attributes here
        ProductSaved::dispatch($entry);
      });
    }

    protected function fetchOrder()
    {
        return $this->fetch(\Backpack\Store\app\Models\Order::class);
    }

    protected function setupListOperation()
    {
        //remove product modifications from list view
        $this->crud->addClause('base');

        // Filter by category
        $this->crud->addFilter([
          'name' => 'category',
          'label' => 'Категория',
          'type' => 'select2',
        ], function(){
          return $this->filter_categories;
        }, function($cat_id){
          $this->crud->query = $this->crud->query->whereHas('categories', function ($query) use ($cat_id) {
              $query->where('category_id', $cat_id);
          });
        });
        
        $this->crud->addColumn([
          'name' => 'imageSrc',
          'label' => '📷',
          'type' => 'image',
          'height' => '60px',
          'width'  => '40px',
        ]);
        
        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => '✅',
          'type' => 'check'
        ]);
        
        $this->crud->addColumn([
          'name' => 'in_stock',
          'label' => '📦',
          'type' => 'number'
        ]);

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название'
        ]);

        $this->crud->addColumn([
          'name' => 'categories',
          'label' => 'Категории',
          'type'  => 'model_function',
          'function_name' => 'getCategoriesString'
          // 'type' => 'relationship',
          // 'attribute' => 'id',
        ]);

        $this->listOperation();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ProductRequest::class);

        if(config('backpack.store.product.modifications.enable', true)) {
          $this->crud->addField([
            'name' => 'parent_id',
            'type' => 'hidden',
            'value' => \Request::query('parent_id') ?? null
          ]);
        }
        
        if(config('backpack.store.product.modifications.enable', true)) {
          $this->crud->addField([
            'name' => 'modifications',
            'label' => 'Модификации',
            'type' => 'modification_switcher',
            'tab' => 'Основное'
          ]);
        }

        // BRAND
        if(config('backpack.store.brands.enable')) {
          $this->crud->addField([
            'name' => 'brand',
            'label' => 'Бренд',
            'type' => 'select2',
            'entity' => 'brand',
            'attribute' => 'name',
            'model' => 'Backpack\Store\app\Models\Brand',
            'tab' => 'Основное',
          ]);
        }

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
        // if($this->entry && !$this->entry->isBase || \Request::get('parent_id')) {
        if(config('backpack.store.product.modifications.enable', true)) {
          $this->crud->addField([
            'name' => 'short_name',
            'label' => 'Краткое название модификации',
            'type' => 'text',
            'tab' => 'Основное'
          ]);
        }
        // }
        
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
        if(config('backpack.store.product.price.enable', true)) {
          $this->crud->addField([
            'name' => 'price',
            'label' => 'Цена',
            'type' => 'number',
            'prefix' => config('backpack.store.currency.symbol'),
            'wrapper'   => [ 
              'class' => 'form-group col-md-6'
            ],
            'attributes' => [
              'step' => 0.01,
              'min' => 0
            ],
            'tab' => 'Основное'
          ]);
        }

        // OLD PRICE
        if(config('backpack.store.product.old_price.enable', true)) {
          $this->crud->addField([
            'name' => 'old_price',
            'label' => 'Старая цена',
            'type' => 'number',
            'prefix' => config('backpack.store.currency.symbol'),
            'wrapper'   => [ 
              'class' => 'form-group col-md-6'
            ],
            'attributes' => [
              'step' => 0.01,
              'min' => 0
            ],
            'tab' => 'Основное'
          ]);
        }
        
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
        if(config('backpack.store.product.code.enable', true)) {
          $this->crud->addField([
            'name' => 'code',
            'label' => 'Артикул',
            'tab' => 'Основное'
          ]);
        }
        
        
        // IMAGES
        if(config('backpack.store.product.images.enable', true)) {
          $this->crud->addField([
            'name'  => 'images',
            'label' => 'Изображения',
            'type'  => 'repeatable',
            'fields' => [
              [
                'name' => 'src',
                'label' => 'Изображение',
                'type' => 'browse',
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
            'default' => [],
            'tab' => 'Изображения'
          ]);
        }
        
        
        // CUSTOM PROPERTIES
        $this->crud->addField([
          'name' => 'delim',
          'type' => 'custom_html',
          'value' => '<h3>Индивидуальные атрибуты</h3>
            <p class="help-block">Уникальные, индивидуальные или малораспространенные свойства товаров.
            Заполняются индивидуально к каждому товару. Выводятся только в характеристиках товара (в фильтрах не исспользуются).
            (Переводы для каждой языковой версии заполняются отдельно).
            </p>',
          'tab' => 'Характеристики'
        ]);

        $this->crud->addField([
          'name' => 'custom_attrs',
          'label' => 'Индивидуальные характеристики',
          'type' => 'table',
          'entity_singular' => 'атрибут',
          'columns'         => [
              'name'  => 'Название',
              'value'  => 'Значение',
          ],
          'min' => 0,
          'fake' => true, 
          'store_in' => 'extras_trans',
          'tab' => 'Характеристики'
        ]);


        $this->crud->addField([
          'name' => 'delim_2',
          'type' => 'custom_html',
          'value' => '<h3>Атрибуты</h3><p class="help-block">Универсальные свойства товаров.
            Создаются и управляются отдельно в разделе <a href="'.url('/admin/attribute').'">Атрибуты</a>.
            Могут быть исспользованы в фильтрах и в характеристиках товара.</p>',
          'tab' => 'Характеристики'
        ]);

        // ATTRIBUTES
        if(config('backpack.store.attributes.enable', true)){
          $this->setAttributesFields();
        }

        // SEO FIELDS
        if(config('backpack.store.product.seo.enable', true)){
          $this->crud->addField([
              'name' => 'meta_title',
              'label' => "Meta Title", 
              'type' => 'text',
              'fake' => true, 
              'store_in' => 'seo',
              'tab' => 'SEO'
          ]);

          $this->crud->addField([
              'name' => 'meta_description',
              'label' => "Meta Description", 
              'type' => 'textarea',
              'fake' => true, 
              'store_in' => 'seo',
              'tab' => 'SEO'
          ]);
        }


        $this->crud->addField([
            'name' => 'in_stock',
            'label' => "Количество в наличии", 
            'default' => 1,
            'type' => 'number',
            'tab' => 'Склад',
            'hint' => 'Кол-во товара будет автоматически вычитаться при совершении заказов на сайте.'
        ]);

      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
    }
        
    /**
     * getAttributeValues
     *
     * @param  mixed $request
     * @param  mixed $attribute_id
     * @return void
     */
    public function getAttributeValues(Request $request, $attribute_id) {
      $search_term = $request->input('q');

      if ($search_term)
      {
          $results = AttributeValue::
                        where('attribute_id', $attribute_id)
                      ->where('value', 'LIKE', '%'.$search_term.'%')
                      ->paginate(20);
      }
      else
      {
          $results = AttributeValue::where('attribute_id', $attribute_id)->paginate(20);
      }

      return $results;
    }
    /**
     * setAttributesFields
     * 
     * Set Attributes create/update fields
     *
     * @return void
     */
    public function setAttributesFields() {
      
      // $this->entry - current product data from DB
      // $this->attrs - collection of all attributes for attached categories
      if(isset($this->attrs) && $this->entry) {

        // Adding hidden field
        $this->crud->addField([
          'name' => 'props',
          'type' => 'hidden',
          'value' => null,
        ]);

        $attr_fields = [];

        //
        foreach($this->attrs as $index => $attribute) {
          // Attribute Model ID
          $id = $attribute->id;

          // Attribute Model values list
          // $available_values = $attribute->values->mapWithKeys(function ($item, $key) {
          //   return [$item['id'] => $item['value']];
          // });

          // Attribute settings
          $settings = $attribute->extras;
          // dd($settings['min'] ?? '1');

          // If entry has attached attributes
          // Try find current value for this attribute 
          if($this->entry->ap) {
            // Find this attribute from already attached attributes
            $model_attribute = $this->entry->ap()->where('attribute_id', $attribute->id)->get();
          }else {
            $model_attribute = null;
          }
          
          // Create base attribute field template
          $si = $attribute->getExtrasTrans('si');

          $base_hint = '';
          $base_hint .= $attribute->in_properties? '<b>В характеристиках</b>': '';
          $base_hint .= $base_hint && mb_strlen($base_hint) > 0 && $attribute->in_filters? ' и ': '';
          $base_hint .= $attribute->in_filters? '<b>В фильтрах</b>': '';
          
          $attr_fields[$index] = [
            'name' => "props[{$id}]",
            'label' => $attribute->name . ($si? ' (' . $si . ')': ''),
            'tab' => 'Характеристики',
            'hint' => $base_hint
          ];

          // Set correct options for different attribute types
          // For checkbox
          if($attribute->type === 'checkbox')
          {
            // IMPORTANT !!!!! CHANGE THIS
            // If exists get pivot value 
            $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
            // dd($value);
            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                // 'name' => 'avsFake',
                'type'    => 'relationship_custom',
                'model2'     => 'Backpack\Store\app\Models\AttributeValue',
                'attribute' => 'value',
                'value' => $value,
                'ajax' => true,
                'multiple' => true,
                // 'entity' => Backpack\Store\app\Models\AttributeValue::class,
                // 'entity' => 'av',
                'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                'placeholder' => "Поиск по названию параметра",
                'minimum_input_length' => 0,
                'inline_create' => [
                  'entity' => 'value',
                  'force_select' => true,
                ]
              ],
              // [
              //   'type'    => 'select2_from_ajax_multiple',
              //   'model'     => 'Backpack\Store\app\Models\AttributeValue',
              //   'attribute' => 'value',
              //   'value' => $value ?? null,
              //   'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
              //   'placeholder' => "Поиск по названию параметра",
              //   'minimum_input_length' => 0
              // ]
            );
          }
          // For radio
          else if($attribute->type === 'radio')
          {
            // IMPORTANT !!!!! CHANGE THIS
            // $value = $model_attribute? $model_attribute->pluck('attribute_value_id')->unique()->toArray(): null;
            // dd($model_attribute);
            $value = $model_attribute->first();
            // dd($value->attribute_value_id);

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type'    => 'select2_from_ajax',
                'model'     => 'Backpack\Store\app\Models\AttributeValue',
                'attribute' => 'value',
                'value' => $value->attribute_value_id ?? null,
                'data_source' => url("/admin/api/attribute_values/" . $attribute->id),
                'placeholder' => "Поиск по названию параметра",
                'minimum_input_length' => 0
              ]
            );
          }
          // For number
          else if($attribute->type === 'number')
          {
            // IMPORTANT !!!!! CHANGE THIS
            $value = $model_attribute->first()->value ?? null;

            $options = [];
            $options['min'] = $settings['min'] ?? 0;
            $options['max'] = $settings['max'] ?? 999999999999;
            $options['step'] = $settings['step'] ?? 0.1;         

            $hint = $attr_fields[$index]['hint'] . ', ';
            $hint .= "мин значение: {$options['min']}, макс значение: {$options['max']}, шаг: {$options['step']}";

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'number',
                'attributes' => [
                  'min' => $options['min'],
                  'max' => $options['max'],
                  'step' => $options['step'],
                ],
                'value' => $value,
                'hint' => $hint
              ]
            );
          }
          // For string
          else if($attribute->type === 'string')
          {
            $value = $model_attribute->first()->value_trans ?? null;

            $attr_fields[$index] = array_merge(
              $attr_fields[$index],
              [
                'type' => 'text',
                'value' => $value,
              ]
            );
          }
        }
        

        // Set all prepared fields
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
    
    /**
     * setAttrsForCategories
     * 
     * Set all attributes for attached categories 
     *
     * @return void
     */
    private function setAttrsForCategories() {
      // if operation type differ from create/update go out
      if(!in_array($this->opr, ['create', 'update']))
        return;

      // create empty collection
      $this->attrs = collect();

      // if categories have not been set go out
      if(!$this->categories || !$this->categories->count())
        return;
      
      // 
      foreach($this->categories as $category) {
        // Take all active attributes for this category 
        $cat_attrs = $category->attributes()->active()->get();

        // If isset active attributes for this category merge with common list
        if($cat_attrs && $cat_attrs->count()) {
          $this->attrs = $this->attrs->merge($cat_attrs);
        }
      }
    }
    
    /**
     * setCategories
     *
     * @return void
     */
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
    
    /**
     * setEntry
     *
     * @return void
     */
    private function setEntry() {
      if($this->crud->getCurrentOperation() === 'update')
        $this->entry = $this->crud->getEntry(\Route::current()->parameter('id'));
      else
        $this->entry = null;
    }
    
    /**
     * setParentEntry
     *
     * @return void
     */
    private function setParentEntry() {
      if(!empty($parent_id = \Request::query('parent_id')))
        $this->parent_entry = $this->crud->getEntry($parent_id);
      elseif($this->entry && $this->entry->parent){
        $this->parent_entry = $this->entry->parent;
      }else{
        $this->parent_entry = null;
      }
    }
    
    /**
     * setOperation
     *
     * @return void
     */
    private function setOperation() {
      $this->opr = $this->crud->getCurrentOperation();
    }
    
    /**
     * setLocale
     *
     * @return void
     */
    private function setLocale() {
      if(\Request::query('locale'))
        app()->setLocale(\Request::query('locale'));
    }
}
