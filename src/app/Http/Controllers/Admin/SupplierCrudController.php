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
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation { update as traitUpdate; }
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;


    use \App\Http\Controllers\Admin\Traits\SupplierCrud;
    
    private $brand_class = null;
    private $ui;

    public function setup()
    {
      $this->brand_class = \Settings::get('dress.supplier.model', 'Backpack\Store\app\Models\Supplier');

      $this->crud->setModel($this->brand_class);
      $this->crud->setRoute(config('backpack.base.route_prefix') . '/supplier');
      $this->crud->setEntityNameStrings('поставщик', 'поставщики');
        

      $this->ui = app(\Backpack\Store\app\Contracts\Admin\SupplierFormStrategy::class);
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

      $this->ui->setupList($this->crud);
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
        // if(\Store::isMulti()){
        //     $this->setMultistoreFields();
        // }

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


      $this->createOperation();
      $this->ui->setupCreateUpdate($this->crud);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }


    // private function setMultistoreFields() {
    //   $this->setRegionsFields();
    //   $this->setCurrencyFields();
    // }
    
    // private function setCurrencyFields() {

    //     $currencies = \Store::currencies();
    //     $data= array_column($currencies, 'name', 'code');

    //     $this->crud->addField([
    //         'name'        => 'currency_code',
    //         'label'       => __('backpack-store::admin.supplier_currency_title'),
    //         'type'        => 'select2_from_array',
    //         'options'     => $data,
    //         'allows_null' => false,
    //         'allows_multiple' => false,
    //     ]);
    // }

    // private function setRegionsFields() {

    //     $countries = \Store::countries();
    //     $localeToCountry = array_column($countries, 'country', 'locale');

    //     $this->crud->addField([
    //         'name'        => 'countries',
    //         'label'       => __('backpack-store::admin.countries'),
    //         'type'        => 'select2_from_array',
    //         'hint'        => __('backpack-store::admin.supplier_countries'),
    //         'options'     => $localeToCountry,
    //         'allows_null' => false,
    //         'allows_multiple' => true,
    //     ]);
    // }

    /**
     * Сохранение связей страна-склад
     */
    protected function saveCountries($supplier, $countries)
    {
        // Удалить старые связи
        \DB::table('ak_supplier_country')->where('supplier_id', $supplier->id)->delete();

        // Добавить новые
        $insert = [];
        foreach ((array) $countries as $code) {
            $insert[] = [
                'supplier_id'  => $supplier->id,
                'country_code' => $code,
            ];
        }
        if ($insert) {
            \DB::table('ak_supplier_country')->insert($insert);
        }
    }



    public function store()
    {
        $response = $this->traitStore();

        $this->saveCountries($this->crud->entry, request()->input('countries', []));
        return $response;
    }

    public function update()
    {
        $response = $this->traitUpdate();

        $this->saveCountries($this->crud->entry, request()->input('countries', []));
        return $response;
    }
}
