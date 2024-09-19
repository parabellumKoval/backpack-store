<?php

namespace Backpack\Store\app\Observers;

use Aimix\Shop\app\Models\Order;

class OrderObserver
{    
    /**
     * deleting
     *
     * @param  mixed $order
     * @return void
     */
    public function deleting($order) {
      if($order->products && !empty($order->products)) {
        $this->stockToProduct($order->products);
      }
    }
    
    /**
     * updated
     *
     * @param  mixed $order
     * @return void
     */
    public function updated($order) {
      $old_status = $order->getOriginal('status');

      if(($old_status !== 'canceled' && $old_status !== 'failed') && ($order->status === 'canceled' || $order->status === 'failed')) {
        $this->stockToProduct($order->products);
      }elseif(($old_status === 'canceled' || $old_status === 'failed') && ($order->status !== 'canceled' && $order->status !== 'failed')) {
        $this->stockToOrder($order->products);
      }
    }
    
    /**
     * stockToOrder
     *
     * @param  mixed $products
     * @return void
     */
    private function stockToOrder($products) {
      foreach($products as $product) {
        try {
          $product->in_stock -= $product->pivot->amount;
          $product->save();
        }catch(\Exception $e) {}
      }
    }
    
    /**
     * stockToProduct
     *
     * @param  mixed $products
     * @return void
     */
    private function stockToProduct($products) {
      foreach($products as $product) {
        try {
          $product->in_stock += $product->pivot->amount;
          $product->save();
        }catch(\Exception $e) {}
      }
    }
}