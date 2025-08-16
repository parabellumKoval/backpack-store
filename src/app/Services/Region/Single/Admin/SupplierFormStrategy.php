<?php

namespace Backpack\Store\app\Services\Region\Single\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;

use Backpack\Store\app\Contracts\Admin\SupplierFormStrategy as Contract;

class SupplierFormStrategy implements Contract {
    public function setupCreateUpdate(CrudPanel $crud): void {
    }

    public function setupList(CrudPanel $crud): void {
    }
}
