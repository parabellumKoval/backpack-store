<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\AttributeRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Backpack\Store\app\Models\AttributeValue;


/**
 * Class AttributeCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class AttributeValueCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    
    private $opr;

    public function setup()
    {
        $this->crud->setModel(AttributeValue::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/value');
        $this->crud->setEntityNameStrings('значение', 'значения');

        // SET OPERATION
        $this->setOperation();
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
       //  $this->crud->setFromDb();
       
       $this->crud->addColumn([
        'name' => 'value',
        'label' => 'Значение',
      ]);
      
      $this->crud->addColumn([
       'name' => 'attribute',
       'label' => 'Атрибут',
       'type' => 'relationship'
     ]);
    }

    protected function setupCreateOperation()
    {

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        
        // if($this->opr !== 'InlineCreate') {
        //   $this->crud->addField([
        //     'name' => 'attribute',
        //     'label' => 'Атрибут',
        //     'type' => 'relationship'
        //   ]);
        // }

        $this->crud->addField([
          'name' => 'attribute',
          'label' => 'Атрибут',
          'type' => 'relationship'
        ]);

        $this->crud->addField([
          'name' => 'value',
          'label' => 'Значение',
          'type' => 'text'
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    private function setOperation() {
      $this->opr = $this->crud->getCurrentOperation();
    }
}
