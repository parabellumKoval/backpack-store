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
        'name' => 'description_mode',
        'label' => 'Режим ИИ-описаний',
        'type' => 'model_function',
        'function_name' => 'getDescriptionModeLabel'
      ]);


      $this->crud->addColumn([
        'name' => 'adminColor',
        'label' => 'Цвет',
        'escaped' => false,
        'limit' => 1500,
      ]);

      // $this->crud->addColumn([
      //   'name' => 'products',
      //   'label' => 'Товары поставщика',
      //   'type' => 'relationship_count',
      //   'suffix' => ' тов.'
      // ]);

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

        // MULTISTORE
        if(config('backpack.multistore.enable', true)){
            $this->setMultistoreFields();
        }

        // COLOR
        $this->crud->addField([
          'name' => 'color',
          'label' => 'Цвет',
          'type' => 'color',
          'fake' => true,
          'store_in' => 'extras',
          'hint' => 'Выберите цвет для визуального различения в админке поставщиков друг от друга.'
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

        // AI DESCRIPTION GENERATION MODE
        $this->crud->addField([
          'name' => 'description_mode',
          'label' => 'Режим генерации описаний ИИ',
          'type' => 'select_from_array',
          'options' => [
            'scratch' => 'С нуля (по названию и бренду)',
            'rewrite' => 'Глубокий рерайт описания поставщика',
          ],
          'default' => 'scratch',
          'allows_null' => false,
          'hint' => 'Определяет, как генератор ИИ создаёт описание для товаров этого поставщика. «Глубокий рерайт» использует импортированное описание поставщика как основу (без выдуманных фактов); требует, чтобы в настройках выгрузки было указано поле «Описание».',
        ]);


      $this->createOperation();
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }


    
    private function setMultistoreFields() {

        $regions = config('backpack.multistore.options', []);
        $regions_filtered = array_filter($regions, function($item) {
            return !isset($item['enabled']) || $item['enabled'] !== false? true: false;
        });

        $localeToCountry = array_column($regions_filtered, 'country', 'locale');

        $this->crud->addField([
            'name'        => 'regions',
            'label'       => "Регионы",
            'type'        => 'select2_from_array',
            'options'     => $localeToCountry,
            'allows_null' => false,
            'allows_multiple' => true,
        ]);
    }
}
