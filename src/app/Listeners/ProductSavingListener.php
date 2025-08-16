<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductSaving;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;
 
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
        // Если у родительского базового товара меняются бренд или категории, у всех детей должны быть установленны значения заново 
        if($product->children()->exists()) {
          // brand
          $product->children()->update(['brand_id' => $product->brand_id]);

          // categories
          $categoryIds = $product->categories()->pluck('ak_product_categories.id')->toArray();
          $product->children()->each(function ($child) use ($categoryIds) {
              $child->categories()->sync($categoryIds);
          });
        }
      }

    }
}