<?php

namespace Aimix\Shop\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\ModificationRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Aimix\Shop\app\Models\Modification;

//use Illuminate\Http\Request;
/**
 * Class ModificationCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class ModificationCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    

    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Modification');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/modification');
        $this->crud->setEntityNameStrings('modification', 'modifications');

    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb();
        
        $this->crud->addFilter([
            'type' => 'simple',
            'name' => 'is_active',
            'label'=> 'Неактивные'
          ],
          false,
          function() {
              $this->crud->addClause('where', 'is_active', '0'); 
          });
          
        
        $this->crud->addColumns([
          [
            'name' => 'product_id',
            'label' => 'Товар',
            'type' => 'select',
            'entity' => 'product',
            'attribute' => 'name',
            'model' => "Aimix\Shop\app\Models\Product",
          ],
          [
            'name' => 'name',
            'label' => 'Название',
          ],
          [
            'name' => 'is_default',
            'label' => 'По умолчанию',
            'type' => 'boolean'
          ],
          [
            'name' => 'is_active',
            'label' => 'Активно',
            'type' => 'boolean'
          ],
          [
            'name' => 'is_pricehidden',
            'label' => 'Цена скрыта',
            'type' => 'boolean'
          ]
        ]);
    }

    protected function setupCreateOperation()
    {
        $this->crud->setValidation(ModificationRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        
        $this->crud->addFields([
          [
            'name' => 'product_id',
            'label' => 'Товар',
            'type' => 'select2',
            'entity' => 'product',
            'attribute' => 'name',
            'model' => "Aimix\Shop\app\Models\Product",
          ],
          [
            'name' => 'code',
            'label' => 'Код/артикул',
          ],
          [
            'name' => 'name',
            'label' => 'Название',
          ],
          [
            'name' => 'slug',
            'label' => 'URL',
            'prefix' => url('/catalog').'/{slug товара}/',
            'hint' => 'По умолчанию будет сгенерирован из названия.',
            'type' => 'text',
          ],
          [
            'name' => 'price',
            'label' => 'Цена',
            'type' => 'number',
          ],
          [
            'name' => 'is_active',
            'label' => 'Активно',
            'type' => 'boolean',
            'default' => '1'
          ],
          [
            'name' => 'is_pricehidden',
            'label' => 'Скрыть цену',
          ],
          
          
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
    protected function removeModification(ModificationRequest $request)
    {
      Modification::destroy($request->id);
      return response()->json(true);
    }
}
