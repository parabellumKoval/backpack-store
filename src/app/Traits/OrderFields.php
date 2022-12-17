<?php

namespace Backpack\Store\app\Traits;

trait OrderFields {
  protected function setupOrderFields() {
    $this->crud->addField([
      'name' => 'orders_amount',
      'label' => 'Кол-во заказов',
      'value' => $this->crud->getEntry(\Route::current()->parameter('id'))->orders->count(),
      'tab' => 'Заказы'
    ]);


    $this->crud->addField([
      'name' => 'orders',
      'type' => 'relationship',
      'label' => "Заказы",
      'tab' => 'Заказы',
      // 'ajax' => true,
      // 'inline_create' => [ 'entity' => 'orders' ]
    ]);
  }

}