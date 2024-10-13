<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\AttributeRequest;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Admin\Attribute as AttributeAdmin;

use Backpack\Store\app\Events\AttributeSaved;

/**
 * Class AttributeCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class AttributeCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    
    // all available types
    private $types;

    // current active attribute type
    private $type;
    // current model instance
    private $entry;

    private $filter_categories;

    public function setup()
    {
        $this->crud->setModel(AttributeAdmin::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/attribute');
        $this->crud->setEntityNameStrings('атрибут', 'атрибуты');
        
        $this->crud->query = $this->crud->query->withoutGlobalScopes();
        
        $this->crud->model->clearGlobalScopes();
        
        $this->types = array_unique(Attribute::pluck('type', 'type')->toArray());

        $this->filter_categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
      

        // CURRENT MODEL
        $this->setEntry();

        // SET ATTRIBUTE TYPE
        $this->setType();

        AttributeAdmin::saving(function($entry) {
          AttributeSaved::dispatch($entry);        
        });
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
       //  $this->crud->setFromDb();

        // Filter by category
        $this->crud->addFilter([
          'name' => 'category',
          'label' => 'Категория',
          'type' => 'select2',
        ], function(){
          $list = ['empty' => '🔴 Без категории'] + $this->filter_categories;
          return $list;
        }, function($id){
          if($id === 'empty') {
            $this->crud->query->has('categories', '=', 0);
          }else {
            $this->crud->query->whereHas('categories', function ($query) use ($id) {
                $query->where('category_id', $id);
            });
          }
        });

        // Filter is active
       $this->crud->addFilter([
            'type' => 'simple',
            'name' => 'is_active',
            'label'=> 'Неактивные'
          ],
          false,
          function() {
              $this->crud->addClause('where', 'is_active', '0'); 
          });
          
        // Filter attribute type
        $this->crud->addFilter([
            'name' => 'type',
            'type' => 'dropdown',
            'label'=> 'Тип значения'
          ], $this->types
          , function($value) {
                $this->crud->addClause('where', 'type', $value);
          });
       

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
        ]);

        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean'
        ]);

        $this->crud->addColumn([
          'name' => 'type',
          'label' => 'Тип',
        ]);
    }
    
    /**
     * setupCreateOperation
     *
     * @return void
     */
    protected function setupCreateOperation()
    {
        $this->crud->setValidation(AttributeRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
        ]);

        $this->crud->addField([
          'name' => 'slug',
          'label' => 'Slug',
          // 'prefix' => url('/attributes').'/',
          'hint' => 'По умолчанию будет сгенерирован из названия',
          'type' => 'text',
        ]);

        $this->setTypeFields();

        $this->crud->addField([
          'name' => 'si',
          'label' => 'Единицы измерения',
          'hint' => 'Единицы измерения будут добавлены после значений',
          'type' => 'text',
          'fake' => true,
          'store_in' => 'extras_trans',
        ]);

        $this->crud->addField([
          'name' => 'categories',
          'label' => 'Категории',
          'type' => 'select2_from_ajax_multiple',
          'select_all' => true,
          'entity' => 'categories',
          'attribute' => 'name',
          'model' => Category::class,
          'data_source' => url("/admin/api/category"),
          'placeholder' => 'Выберите категорию',
          'minimum_input_length' => 2,
          'hint' => 'Категории товаров к которым применимы данные характеристики',
        ]);

        $this->crud->addField([
          'name' => 'in_filters',
          'label' => 'Добавить в фильтрацию',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в фильтрации в каталоге',
          'default' => 1
        ]);

        $this->crud->addField([
          'name' => 'in_properties',
          'label' => 'Добавить в характеристики',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в характеристиках товара',
          'default' => 1
        ]);

        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет активен',
          'default' => 1
        ]);

        if(config('backpack.store.attribute.enable_icon')) {
          $this->crud->addField([
            'name' => 'icon',
            'label' => 'Иконка',
            'type' => 'textarea',
            'attributes' => [
              'rows' => '7'
            ],
            'hint' => 'html-код иконки',
          ]);
        }

        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor', 
        ]);
    }
        
    /**
     * setCountableField
     *
     * @return void
     */
    protected function setTypeFields() {
      $js_attributes = [
        'data-value' => '',
        'onfocus' => "this.setAttribute('data-value', this.value);",
        'onchange' => "
          const value = event.target.value
          let isConfirmed = confirm('Несохраненные данные будут сброшены. Все равно продолжить?');
          
          if(isConfirmed) {
            reload_page(event);
          } else{
            this.value = this.getAttribute('data-value');
          }

          function reload_page(event) {
            const value = event.target.value
            url = insertParam('type', value)
          };

          function insertParam(key, value) {
            key = encodeURIComponent(key);
            value = encodeURIComponent(value);
        
            // kvp looks like ['key1=value1', 'key2=value2', ...]
            var kvp = document.location.search.substr(1).split('&');
            let i=0;
        
            for(; i<kvp.length; i++){
                if (kvp[i].startsWith(key + '=')) {
                    let pair = kvp[i].split('=');
                    pair[1] = value;
                    kvp[i] = pair.join('=');
                    break;
                }
            }
        
            if(i >= kvp.length){
                kvp[kvp.length] = [key,value].join('=');
            }
        
            // can return this or...
            let params = kvp.join('&');
        
            // reload page with new params
            document.location.search = params;
          }
        "
      ];

      $this->crud->addField([
        'name' => 'type',
        'label' => 'Тип значения',
        'type' => 'select_from_array',
        'options' => Attribute::$TYPES,
        'attributes' => $js_attributes,
        'value' => $this->type
      ]);

      if($this->type === 'checkbox' || $this->type === 'radio' ) {
        $this->crud->addField([
          'name' => 'values',
          'label' => 'Допустимые значения',
          'type' => 'relationship',
          'ajax' => true,
          'inline_create' => [
            'entity' => 'value',
            'force_select' => true
          ]
        ]);
      } else if($this->type === 'number') {
        $this->crud->addField([
          'name' => 'min',
          'label' => 'Минимальное значение',
          'type' => 'number',
          'fake' => true,
          'store_in' => 'extras',
          'attributes' => ["step" => 0.0001],
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
        ]);
        $this->crud->addField([
          'name' => 'max',
          'label' => 'Максимальное значение',
          'type' => 'number',
          'fake' => true,
          'store_in' => 'extras',
          'attributes' => ["step" => 0.0001],
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
        ]);
        $this->crud->addField([
          'name' => 'step',
          'label' => 'Шаг',
          'type' => 'number',
          'fake' => true,
          'store_in' => 'extras',
          'attributes' => ["step" => 0.0001],
          'wrapper'   => [ 
            'class' => 'form-group col-md-4'
          ],
        ]);
      }
    }
    /**
     * setupUpdateOperation
     *
     * @return void
     */
    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
      $this->crud->modifyField('type', [
        'attributes' => [
          'readonly' => 'readonly',
          'disabled' => 'disabled'
        ],
      ]);
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
     * setType
     *
     * @return void
     */
    private function setType() {
      $request_type = \Request::get('type', null);
      
      if($request_type) {
        $this->type = $request_type;
      }elseif($this->entry) {
        $this->type = $this->entry->type;
      }else {
        $this->type = 'checkbox';
      }
    }

        
    /**
     * fetchValues
     *
     * @return void
     */
    public function fetchValues()
    {
      // We have to get attribute id field
      $request = request()->all();
      
      // Find attribute field
      $id_field = array_filter($request['form'], function($item) {
        if($item['name'] === 'id'){
          return true;
        }else {
          return false;
        }
      });

      // Get attribute id
      $attribute_id = array_values($id_field)[0]['value'];

      return $this->fetch([
        'model' => AttributeValue::class,
        'searchable_attributes' => ['value'],
        'paginate' => 20,
        'query' => function($model) use ($attribute_id) {
            return $model->where('attribute_id', $attribute_id);
        }
      ]);
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
}
