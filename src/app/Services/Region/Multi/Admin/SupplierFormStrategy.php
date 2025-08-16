<?php

namespace Backpack\Store\app\Services\Region\Multi\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;

use Backpack\Store\app\Contracts\Admin\SupplierFormStrategy as Contract;

class SupplierFormStrategy implements Contract {
    public function setupCreateUpdate(CrudPanel $crud): void {

      $this->setMultistoreFields($crud);
    }

    public function setupList(CrudPanel $crud): void {
        $crud->addColumn([
            'name'        => 'currency_code',
            'label'       => __('backpack-store::admin.supplier_currency_title'),
        ]);

        $crud->addColumn([
            'name'        => 'countries',
            'label'       => __('backpack-store::admin.countries'),
            'type'        => 'select_from_array',
            'hint'        => __('backpack-store::admin.supplier_countries'),
            'options'     => $this->getCountriesArray(),
        ]);
    }
    
    private function setMultistoreFields($crud) {
      $this->setCurrencyFields($crud);
      $this->setRegionsFields($crud);
    }
    
    private function setCurrencyFields($crud) {
        $crud->addField([
            'name'        => 'currency_code',
            'label'       => __('backpack-store::admin.supplier_currency_title'),
            'type'        => 'select2_from_array',
            'options'     => $this->getCurrenciesArray(),
            'allows_null' => false,
            'allows_multiple' => false,
        ])->afterField('name');
    }

    private function setRegionsFields($crud) {
        $crud->addField([
            'name'        => 'countries',
            'label'       => __('backpack-store::admin.countries'),
            'type'        => 'select2_from_array',
            'hint'        => __('backpack-store::admin.supplier_countries'),
            'options'     => $this->getCountriesArray(),
            'allows_null' => false,
            'allows_multiple' => true,
        ])->afterField('name');
    }

    private function getCurrenciesArray() {
        $currencies = \Store::currencies();
        $data= array_column($currencies, 'name', 'code');
        return $data;
    }

    private function getCountriesArray() {
        $countries = \Store::countries();
        $localeToCountry = array_column($countries, 'country', 'locale');
        return $localeToCountry;
    }
}
