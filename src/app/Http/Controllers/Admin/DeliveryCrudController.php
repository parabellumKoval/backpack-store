<?php

namespace Aimix\Shop\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\DeliveryRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

/**
 * Class DeliveryCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class DeliveryCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    private $languages = 'ru';
    
    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Delivery');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/delivery');
        $this->crud->setEntityNameStrings('способ доставки', 'способы доставки');
        
        $this->languages = config('backpack.crud.locales');
	      $this->crud->query = $this->crud->query->withoutGlobalScopes();
	      $this->crud->model->clearGlobalScopes();
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        //$this->crud->setFromDb();
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
          
        }
          
          $this->crud->addColumn([
            'name' => 'language_abbr',
            'label' => 'Регион',
          ]);
        
        $this->crud->addColumn([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean'
        ]);
        
        $this->crud->addColumn([
          'name' => 'name',
          'label' => 'Название',
        ]);
        
        $this->crud->addColumn([
          'name' => 'price',
          'label' => 'Стоимость',
        ]);
        $this->crud->addColumn([
            'name' => 'type',
            'label' => 'Тип',
          ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(DeliveryRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        //$this->crud->setFromDb();
        $this->crud->addField([
          'name' => 'language_abbr',
          'label' => 'Язык',
          'type' => 'select_from_array',
          'options' => $this->languages
        ]);
        
        $this->crud->addField([
          'name' => 'type',
          'label' => 'Тип',
          'type' => 'select_from_array',
          'options' => ['time' => 'Время', 'method' => 'Способ']
        ]);
        
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => 1
        ]);
        
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название'
        ]);
        
        $this->crud->addField([
          'name' => 'slug',
          'label' => 'URL',
          'prefix' => url('/delivery').'/',
          'hint' => 'По умолчанию будет сгенерирован из названия.'
        ]);
        
        $this->crud->addField([
          'name' => 'price',
          'label' => 'Стоимость',
          'type' => 'number',
        ]);
        
        $this->crud->addField([
          'name' => 'image',
          'label' => 'Изображение',
          'type' => 'browse',
        ]);

        $this->crud->addField([
          'name' => 'icon',
          'label' => 'Иконка',
          'type' => 'textarea',
          'attributes' => [
            'rows' => '7'
          ],
          'hint' => 'html-код иконки',
        ]);
        
        $this->crud->addField([
          'name' => 'description',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 8,
          ]
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
