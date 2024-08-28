<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductCreating;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;
 
class ProductCreatingListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(){}
 

    /**
     * Handle the event.
     *
     * @param  \App\Events\ProductCreating  $event
     * @return void
     */
    public function handle(ProductCreating $event)
    {
      if(config('backpack.store.supplier.enable', false) && !empty($event->product->default_supplier)) {
        $event->product->default_supplier['in_stock'] = 1000;
      }
    }
}