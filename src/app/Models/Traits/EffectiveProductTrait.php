<?php

namespace Backpack\Store\app\Models\Traits;

trait EffectiveProductTrait {  

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /**
     * getSimpleInStockAttribute
     *
     * @return void
     */
    public function getSimpleInStockAttribute() {
      if(config('backpack.store.supplier.enable', false)) {
        return $this->currentSp->in_stock ?? 0;
      }else {
        return $this->in_stock;
      }
    }

        
    /**
     * getSimplePriceAttribute
     *
     * @return void
     */
    public function getSimplePriceAttribute() {
      if(config('backpack.store.supplier.enable', false)) {
        return $this->currentSp->price ?? 0;
      }else {
        return $this->price;
      }
    }
    
    /**
     * getSimpleOldPriceAttribute
     *
     * @return void
     */
    public function getSimpleOldPriceAttribute() {
      if(config('backpack.store.supplier.enable', false)) {
        return $this->currentSp->old_price ?? 0;
      }else {
        return $this->old_price;
      }
    }

    /**
     * getSimpleCodeAttribute
     *
     * @return void
     */
    public function getSimpleCodeAttribute() {
      if(!empty($this->code))
        return $this->code;

      $sp = $this->currentSp;

      if($sp) {
        if(!empty($sp->code)) {
          return $sp->code;
        }elseif(!empty($sp->barcode)) {
          return $sp->barcode;
        }else {
          return null;
        }
      }
    }
}