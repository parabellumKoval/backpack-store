<?php

namespace Backpack\Store\app\Contracts\Admin;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanel;

interface SupplierFormStrategy {
    public function setupCreateUpdate(CrudPanel $crud): void;
    public function setupList(CrudPanel $crud): void;
}
