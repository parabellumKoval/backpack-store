<?php

namespace Backpack\Store\app\Http\Controllers\Admin;

use Backpack\Store\app\Http\Requests\PromocodeRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Illuminate\Database\Eloquent\Builder;

// MODELS
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Http\Controllers\Admin\Base\ProductCrudBase;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class PromocodeCrudController extends ProductCrudBase
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\InlineCreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    //use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    use \App\Http\Controllers\Admin\Traits\PromocodeCrud;
    
    private $categories;
    private $filter_categories;
    private $brands;
    
    public function setup()
    {
        $this->crud->setModel('Backpack\Store\app\Models\Admin\Promocode');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/promocode');
        $this->crud->setEntityNameStrings('купон', 'купоны'); 
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
          'name' => 'code',
          'label' => 'Промокод'
        ]);
        
        $this->crud->addColumn([
          'name' => 'used_to_limit',
          'label' => 'Исп./Лимит',
        ]);

        $this->crud->addColumn([
          'name' => 'valid_until',
          'label' => 'Годен до',
          'type' => 'datetime'
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(PromocodeRequest::class);

        // IS ACTIVE
        $this->crud->addField([
          'name' => 'is_active',
          'label' => 'Активен',
          'type' => 'boolean',
          'default' => '1',
        ]);
        
        // Promocode
        $this->crud->addField([
          'name' => 'code',
          'label' => 'Промокод',
          'type' => 'text',
        ]);
        
        // NAME
        $this->crud->addField([
          'name' => 'name',
          'label' => 'Название',
          'type' => 'text',
        ]);

        // Type
        $this->crud->addField([
          'name' => 'type',
          'label' => 'Тип скидки',
          'type' => 'select_from_array',
          'options' => [
            'percent' => 'Процент',
            'value' => 'Сумма'
          ],
          'default' => 'percent',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
        ]);
    
        // Value
        $this->crud->addField([
          'name' => 'value',
          'label' => 'Размер скидки',
          'type' => 'number',
          'prefix' => '$',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
          'attributes' => [
            'step' => 0.01,
            'min' => 0
          ],
        ]);
    
        // Limit
        $this->crud->addField([
          'name' => 'limit',
          'label' => 'Лимит по количеству использований',
          'type' => 'number',
          'default' => 0,
          'attributes' => [
            'step' => 1,
            'min' => 0
          ],
          'hint' => '0 - безлимитное количество использований',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
        ]);
    
        // valid until
        $this->crud->addField([
          'name' => 'valid_until',
          'label' => 'Лимит по дате и времени',
          'type' => 'datetime_picker',
          'hint' => 'Если не заполнять срок действия будет беcсрочным',
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
        ]);

        // used_times
        $this->crud->addField([
          'name' => 'used_times',
          'label' => 'Уже применен раз',
          'type' => 'number',
          'default' => 0,
          'attributes' => [
            'readonly' => true
          ],
          'wrapper'   => [ 
            'class' => 'form-group col-md-6'
          ],
        ]);

    
      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
      $this->setupCreateOperation();
    }
    
}
