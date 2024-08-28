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
          // If Suppliers enabled manipulate throw sp relation
          if(config('backpack.store.supplier.enable', true)) {
            $product->currentSp->in_stock -= $product->pivot->amount;

            if($product->currentSp->in_stock < 0) {
              $product->currentSp->in_stock = 0;
            }

            $product->currentSp->save();
          // else base product in_stock enabled
          }else {
            $product->in_stock = $product->in_stock - $product->pivot->amount;

            if($product->in_stock < 0) {
              $product->in_stock = 0;
            }

            $product->save();
          }
        }
      }

    }
}