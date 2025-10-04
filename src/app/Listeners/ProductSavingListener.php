<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductSaving;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

use Backpack\Store\app\Job\UpdateProductModificationsVertical;

class ProductSavingListener
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
     * @param  \App\Events\ProductSaving  $event
     * @return void
     */
    public function handle(ProductSaving $event)
    {
      $this->handleChildrenProductCreating($event->product);
    }

    private function handleChildrenProductCreating($product) {
      if($product->parent_id) {
        $parent = Product::find($product->parent_id);

        // Если это сохранение модификации товара
        // Мы разрешаем не заполнять вручную название товара,
        // в таком случае название должно сформироваться автоматически на основании названия базового товара + краткое название этой модификации 
        if(empty($product->name) && $parent) {
          $product->name = "{$parent->name} - {$product->short_name}";
        }
      }
      

      if(\Store::isModVertical()) {
        UpdateProductModificationsVertical::dispatch($product);
      }

    }
}