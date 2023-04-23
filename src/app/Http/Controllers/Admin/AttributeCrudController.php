<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\AttributeRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;


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
    
    private $types;
    private $units;

    public function setup()
    {
        $this->crud->setModel('Backpack\Store\app\Models\Attribute');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/attribute');
        $this->crud->setEntityNameStrings('атрибут', 'атрибуты');
        
        $this->crud->query = $this->crud->query->withoutGlobalScopes();
        Category::first();
        $this->crud->model->clearGlobalScopes();
        
        $this->types = array_unique(Attribute::pluck('type', 'type')->toArray());
        $this->units = array_unique(Attribute::pluck('si', 'si')->toArray());
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
       //  $this->crud->setFromDb();
       $this->crud->addFilter([
            'type' => 'simple',
            'name' => 'is_active',
            'label'=> 'Неактивные'
          ],
          false,
          function() {
              $this->crud->addClause('where', 'is_active', '0'); 
          });
          
        $this->crud->addFilter([
            'name' => 'type',
            'type' => 'dropdown',
            'label'=> 'Тип значения'
          ], $this->types
          , function($value) {
                $this->crud->addClause('where', 'type', $value);
          }); 
              
          $this->crud->addFilter([
            'type' => 'dropdown',
            'name' => 'si',
            'label'=> 'Единицы измерения'
          ], 
          $this->units, 
          function($value) {
              $this->crud->addClause('where', 'si', $value);
        });
       

        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
        ]);

      if(config('aimix.shop.enable_attribute_groups')) {
        $this->crud->addColumn([
          'name' => 'attribute_group_id',
          'label' => 'Группа',
          'type' => 'select',
          'entity' => 'AttributeGroup',
          'attribute' => 'name',
          'model' => "Aimix\Shop\app\Models\AttributeGroup",
        ]);
      }

        $this->crud->addColumn([
          'name' => 'si',
          'label' => 'Единицы измерения',
        ]);

        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean'
        ]);
    }

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
          'label' => 'URL',
          'prefix' => url('/characteristics').'/',
          'hint' => 'По умолчанию будет сгенерирован из названия.',
          'type' => 'text',
        ]);

        $this->crud->addField([
          'name' => 'values',
          'type' => 'hidden',
          'value' => ''
        ]);


        $this->crud->addField([
          'name' => 'type',
          'label' => 'Тип значения',
          'type' => 'type_configurator',
          'options_name' => 'values'
        ]);

        $this->crud->addField([
          'name' => 'default_value',
          'label' => 'Значение по-умолчанию',
          'type' => 'text',
        ]);

        $this->crud->addField([
          'name' => 'si',
          'label' => 'Единицы измерения',
          'hint' => 'Единицы измерения будут добавлены после значений',
          'type' => 'text',
        ]);

        $this->crud->addField([
          'name' => 'categories',
          'label' => 'Категории',
          'type' => 'select2_multiple',
          'select_all' => true,
          'entity' => 'categories',
          'attribute' => 'name',
          'model' => Category::class,
          'pivot' => true,
          'hint' => 'Категории товаров к которым применимы данные характеристики',
          'options'   => (function ($query) {
              return $query->withoutGlobalScopes()->get();
          }),
        ]);

        $this->crud->addField([
          'name' => 'is_important',
          'label' => 'Добавить в основные характеристики',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в основных характеристиках товара',
        ]);

        $this->crud->addField([
          'name' => 'in_filters',
          'label' => 'Добавить в фильтрацию',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в фильтрации в каталоге',
        ]);

        $this->crud->addField([
          'name' => 'in_properties',
          'label' => 'Добавить в характеристики',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный атрибут будет отображаться в характеристиках товара',
        ]);

        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'checkbox',
          'hint' => 'Если включено, то данный тип будет активен',
        ]);

      if(config('aimix.shop.enable_attribute_icon')) {
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
          'name' => 'description',
          'label' => 'Описание',
          'type' => 'ckeditor', 
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
