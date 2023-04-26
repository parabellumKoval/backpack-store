<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductAttachedToOrder;
 
class ProductAttachedToOrderListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {

    }
 
    /**
     * Handle the event.
     *
     * @param  \App\Events\ProductAttachedToOrder  $event
     * @return void
     */
    public function handle(ProductAttachedToOrder $event)
    {
      // Change product in_stock 
      if($event->order->products && $event->order->products->count() && !config('backpack.store.product.in_stock.fixed', false)){
        foreach($event->order->products as $product){
          $product->in_stock = $product->in_stock - $product->pivot->amount;
          $product->in_stock = $product->in_stock < 0? 0: $product->in_stock;
          $product->save();
        }
      }

    }
}