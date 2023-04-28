<?php

namespace Backpack\Store\app\Traits\Admin;

trait ProductFields {
  public function setupUpdateFields() {
    $this->crud->addField([
      'name' => 'test',
      'label' => 'Кол-во заказов',
      'tab' => 'Заказы'
    ]);
  }
}