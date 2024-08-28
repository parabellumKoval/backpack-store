<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

// use Backpack\Store\app\Http\Requests\UploadRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class UploadCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;


    use \App\Http\Controllers\Admin\Traits\UploadCrud;
    
    private $brand_class = null;

    public function setup()
    {
      $this->brand_class = config('backpack.store.source.upload_class', 'Backpack\Store\app\Models\Upload');

        $this->crud->setModel($this->brand_class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/upload');
        $this->crud->setEntityNameStrings('история выгрузки', 'истории выгрузки');
        
    }

    protected function setupListOperation()
    {
      $this->crud->addColumn([
        'name' => 'source',
        'label' => 'Источник',
        'type' => 'relationship'
      ]);

      $this->crud->addColumn([
        'name' => 'statusAdmin',
        'label' => 'Статус',
        'type' => 'model_function',
        'function_name' => 'getStatusAdminAttribute',
        'limit' => 1000,
      ]);

      $this->crud->addColumn([
        'name' => 'countProcessedAdmin',
        'label' => 'Обработано',
      ]);

      $this->crud->addColumn([
        'name' => 'new_items',
        'label' => 'Новых',
      ]);
      $this->crud->addColumn([
        'name' => 'updated_items',
        'label' => 'Обновлено',
      ]);
      $this->crud->addColumn([
        'name' => 'error_items',
        'label' => 'Ошибок',
      ]);
      $this->crud->addColumn([
        'name' => 'createdAtHuman',
        'label' => 'Создано',
      ]);

      // $this->listOperation();
    }

    protected function setupCreateOperation()
    {
      // $this->crud->setValidation(UploadRequest::class);

      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
