<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\AttributeRequest;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

// MODELS
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\Admin\Attribute as AttributeAdmin;

//EVENTS
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
    use \Backpack\CRUD\app\Http\Controllers\Operations\BulkDeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ServiceOperation;
    use \Backpack\Helpers\Traits\Admin\HasToggleColumns;
    
    // all available types
    private $types;

    // current active attribute type
    private $type;

    // current model instance
    private $entry;

    private $lang;

    private $filter_categories;

    private $langs_list;
    private $available_languages;

    public function setup()
    {
        $this->crud->setModel(AttributeAdmin::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/attribute');
        $this->crud->setEntityNameStrings('атрибут', 'атрибуты');
        
        // current language
        $this->lang = backpack_translatable_request_locale(config('app.locale'));

        // languages
        $this->available_languages = config('backpack.crud.locales');
        $this->langs_list = array_keys($this->available_languages);

        $this->crud->query = $this->crud->query->withoutGlobalScopes();
        
        $this->crud->model->clearGlobalScopes();
        
        // $this->types = array_unique(Attribute::pluck('type', 'type')->toArray());

        $this->filter_categories = Category::withoutGlobalScopes()->NoEmpty()->pluck('name', 'id')->toArray();
      

        // CURRENT MODEL
        $this->setEntry();

        // SET ATTRIBUTE TYPE
        $this->setType();

        AttributeAdmin::saved(function($entry) {
          AttributeSaved::dispatch($entry);        
        });
    }

    protected function setupListOperation()
    {
        $langs_list = $this->langs_list;

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
          ], Attribute::$TYPES
          , function($value) {
                $this->crud->addClause('where', 'type', $value);
          });
       

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
          'searchLogic' => function ($query, $column, $searchTerm) use($langs_list) {
            $query->where(function($query) use ($searchTerm, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($searchTerm)).'%']);
              }
            });
          },
        ]);

        $this->addToggleColumn([
            'name' => 'is_active',
            'label' => 'Активен',
        ]);

        $this->crud->addColumn([
          'name' => 'type',
          'label' => 'Тип',
          'type' => 'select_from_array',
          'options' => Attribute::$TYPES
        ]);

        $this->crud->addColumn([
          'name' => 'si',
          'label' => 'Си'
        ]);

        $this->crud->addColumn([
          'name' => 'categories',
          'label' => 'Кол-во категорий',
          'type'  => 'relationship_count',
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
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name' => 'slug',
          'label' => 'Slug',
          // 'prefix' => url('/attributes').'/',
          'hint' => 'По умолчанию будет сгенерирован из названия',
          'type' => 'text',
          'tab' => 'Основное'
        ]);

        $this->setTypeFields();

        $this->crud->addField([
          'name' => 'categories',
          'label' => 'Категории',
          'type' => 'select2_from_ajax_multiple',
          'select_all' => true,
          'entity' => 'categories',
          'attribute' => 'uniqHtml',
          'model' => Category::class,
          'data_source' => route('backpack.helpers.fetch', ['key' => 'category']),
          'placeholder' => 'Выберите категорию',
          'minimum_input_length' => 2,
          'hint' => 'Категории товаров к которым применимы данные характеристики',
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name' => 'in_filters',
          'label' => 'Добавить в фильтрацию',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в фильтрации в каталоге',
          'default' => 1,
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name' => 'in_properties',
          'label' => 'Добавить в характеристики',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в характеристиках товара',
          'default' => 1,
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет активен',
          'default' => 1,
          'tab' => 'Основное'
        ]);

        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'tab' => 'Основное'
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
        'value' => $this->type,
        'tab' => 'Значения'
      ]);

      $this->crud->addField([
        'name' => 'si',
        'label' => 'Единицы измерения',
        'hint' => 'Единицы измерения будут добавлены после значений',
        'type' => 'text',
        'fake' => true,
        'store_in' => 'extras_trans',
        'tab' => 'Значения'
      ]);

      if($this->type === 'checkbox' || $this->type === 'radio' ) {
        // $this->crud->addField([
        //   'name' => 'values',
        //   'label' => 'Допустимые значения',
        //   'type' => 'relationship',
        //   'ajax' => true,
        //   'inline_create' => [
        //     'entity' => 'value',
        //     'force_select' => true
        //   ],
        //   'tab' => 'Значения'
        // ]);

        $this->crud->addField([
          'name' => 'delim',
          'type' => 'custom_html',
          'value' => '<h4>Допустимые значения</h4>
          <ul>
            <li>Поля <b>Тип действия</b> и <b>Значение (для действия)</b> надо заполнять только если необходимо трансформировать значение</li>
            <li>Со значениями можно проводить такие операции: объединение (для устранения дублей), разделение</li>
            <li>Если хотите Объединить значение с другим: в поле <b>Тип действия</b> выберите соответсвующую операцию, а в поле <b>Значение (для действия)</b> введите точное Значение поля с которым хотите объединить данное.</li>
            <li>Если хотите Разделить значение на несколько: в поле <b>Тип действия</b> выберите соответсвующую операцию, а в поле <b>Значение (для действия)</b> введите одно или несколько значений на которые хотите разделить данное. Например <code>Значение 1|Значение 2</code></li>
            <li>Текущее значение после операции будет удалено</li>
            <li>При операции разделения несуществующие значения будут добавлены в список допустимых значений</li>
            <li>Если значение было присвоено какому-либо товару, старое значение будет удалено, а товару будут присвоены новые значения (после операций объединения или разделения)</li>
            <li>Операции со значениями будут обработаны по графику: каждые 10 минут</li>
          </ul>',
          'tab' => 'Значения',
        ]);

        $this->crud->addField([
          'name' => 'values_array',
          'label' => '',
          'type' => 'repeatable',
          'fields' => [
            [
              'name'    => 'id',
              'type'    => 'hidden',
            ],
            [
              'name'    => 'value',
              'type'    => 'text',
              'label'   => 'Значение',
              'wrapper' => ['class' => 'form-group col-md-6'],
            ],
            [
              'name'    => 'slug',
              'type'    => 'text',
              'label'   => 'Slug',
              'wrapper' => ['class' => 'form-group col-md-6'],
            ],
            [
              'name'    => 'transform',
              'label'   => 'Тип действия',
              'type'    => 'select_from_array',
              'default' => null,
              'allows_null' => true,
              'options' => [
                'join' => 'Объединить с другим значением (а это удалить)',
                'split' => 'Разделить на несколько значений (а это удалить)',
              ],
              'wrapper' => ['class' => 'form-group col-md-4'],
            ],
            [
              'name' => 'transform_value',
              'label' => 'Значение (для действия)',
              'type' => 'text',
              'init_rows' => 0,
              // 'hint' => '',
              'wrapper' => ['class' => 'form-group col-md-8'],
            ]
          ],
          'new_item_label'  => 'Добавить значение',
          'init_rows' => 0,
          'value' => $this->getAttributeValuesArray(),
          'tab' => 'Значения',
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
          'tab' => 'Значения'
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
          'tab' => 'Значения'
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
          'tab' => 'Значения'
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

      if(in_array($this->entry->type, ['radio', 'checkbox'])) {
        $this->crud->modifyField('type', [
          'options' => Attribute::$SELECT_TYPES,
        ]);
      }else {
        $this->crud->modifyField('type', [
          'attributes' => [
            'readonly' => 'readonly',
            'disabled' => 'disabled',
          ],
        ]);
      }
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
     * getAttributeValues
     *
     * @return void
     */
    private function getAttributeValuesArray(): array{
      if(empty($this->entry)) return [];

      $values = $this->entry->values;
      return $values->map(function($item) {
        
        $value = $item->getTranslation('value', $this->lang, true);

        if(empty($value)) {
          foreach($this->langs_list as $lang) {
            $value = $item->getTranslation('value', $lang, true);

            if(!empty($value)) {
              break;
            }
          }
        }
        
        return [
          'id' => $item->id,
          'value' => $value,
          'slug' => $item->slug,
          'transform' => $item->transform,
          'transform_value' => $item->transformValueString
        ];
      })->toArray();
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

      // langs
      $langs_list = $this->langs_list;

      if ($search_term)
      {
          $results = AttributeValue::
            where(function($query) use ($search_term, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(value, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($search_term)).'%']);
              }
            })
            ->where('attribute_id', '=', $attribute_id)
            ->paginate(20);
      }
      else
      {
          $results = AttributeValue::where('attribute_id', $attribute_id)->paginate(20);
      }

      return $results;
    }

    /**
     * getAttribute
     *
     * @param  mixed $request
     * @param  mixed $attribute_id
     * @return void
     */
    public function getAttribute(Request $request, $category_id) {
      if($category_id) {
        return Attribute::whereRelation('categories', 'ak_attribute_category.category_id', $category_id)->get()->mapWithKeys(function (Attribute $item) {
            return [$item->id => $item->name];
        });
      }

      $search_term = $request->input('q');
      $ids = $request->input('keys');

      if($ids) {
        $search_key_array = is_numeric($ids)? [$ids]: json_decode($ids, true);
        $attributes = Attribute::whereIn('id', $search_key_array)->get();

        return $attributes;
      }

      // langs
      $langs_list = $this->langs_list;

      if ($search_term)
      {
          $results = Attribute::
            where(function($query) use ($search_term, $langs_list){
              foreach($langs_list as $index => $lang_key) {
                $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                $query->{$function_name}('LOWER(JSON_EXTRACT(name, "$.' . $lang_key . '")) LIKE ? ', ['%'.trim(mb_strtolower($search_term)).'%']);
              }
            })
            ->where('is_active', 1)
            ->where('in_filters', 1)
            ->paginate(20);
      }
      else
      {
          $results = Attribute::paginate(20);
      }

      return $results;
    }
}
