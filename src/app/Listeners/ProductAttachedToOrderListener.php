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
      if($event->order->products && $event->order->products->count()){
        foreach($event->order->products as $product){
          $product->adjustStock(-$product->pivot->amount);
        }
      }

    }
}