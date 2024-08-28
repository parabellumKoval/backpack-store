<?php

namespace App\Http\Controllers\Admin\Traits;

use Backpack\Store\app\Traits\Exchange;

trait SourceCrud {

  use Exchange;
  
  // Extends of SetupOperation
  public function setupOperation() {}

  // Extends of ListOperation
  public function listOperation() {}

  // Extends of CreateOperation
  public function createOperation() {}


  /**
   * getExchangeRate
   *
   * @return void
   */
  private function getExchangeRate() {
    return $this->getRate();
  }
}