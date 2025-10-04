<?php

namespace Backpack\Store\app\Http\Controllers\Admin\Traits;

trait BaseCrudTrait
{
  public $entry = null;
  /**
   * setEntry
   *
   * @return void
   */
  private function setEntry() {
    if($this->crud->getCurrentOperation() === 'update')
      $this->entry = $this->crud->getEntry(\Route::current()->parameter('id'));
    else
      $this->entry = null;
  }
}