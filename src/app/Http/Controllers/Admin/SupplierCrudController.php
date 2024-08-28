<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\BrandRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\LangFileManager\app\Models\Language;

/**
 * Class SupplierCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class SupplierCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;


    use \App\Http\Controllers\Admin\Traits\SupplierCrud;
    
    private $brand_class = null;

    public function setup()
    {
      $this->brand_class = config('backpack.store.supplier.class', 'Backpack\Store\app\Models\Supplier');

        $this->crud->setModel($this->brand_class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/supplier');
        $this->crud->setEntityNameStrings('поставщик', 'поставщики');
        
    }

    protected function setupListOperation()
    {
      $this->crud->addColumn([
        'name' => 'is_active',
        'label' => '✅',
        'type' => 'check'
      ]);

      $this->crud->addColumn([
        'name' => 'name',
        'label' => 'Название'
      ]);

      $this->crud->addColumn([
        'name' => 'type',
        'label' => 'Тип'
      ]);

      $this->crud->addColumn([
        'name' => 'products',
        'label' => 'Товары поставщика',
        'type' => 'relationship_count',
        'suffix' => ' тов.'
      ]);

      $this->listOperation();
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(BrandRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
      

        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
        ]);
        
        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
        ]);

        // DESCRIPTION
        $this->crud->addField([
          'name' => 'content',
          'label' => 'Описание',
          'type' => 'ckeditor',
          'attributes' => [
            'rows' => 7
          ]
        ]);
        
        $this->crud->addField([
          'name' => 'type',
          'label' => 'Тип',
          'type' => 'select_from_array',
          'options' => [
            'warehouse' => 'Склад',
            'dropshipping' => 'Дропшипинг',
            'common' => 'Общее',
          ]
        ]);


      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
