<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

use Backpack\Store\app\Job\UpdateProductModificationsHorizontall;
use Backpack\Store\app\Job\RemoveAllProductModificationsHorizontall;
 
use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;

class ProductSavedListener
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
     * @param  \App\Events\ProductSaved  $event
     * @return void
     */
    public function handle(ProductSaved $event)
    {
      $suppliersData = $event->product->suppliers_data;
      $defaultSupplier = $event->product->default_supplier;

      if ($suppliersData !== null) {
        if(\Settings::get('dress.supplier.enable', false)) {
          $this->setMultipleSuppliers($event->product, $suppliersData);
        }else {
          $this->setDefaultSupplier($event->product, $suppliersData);
        }
      }elseif($defaultSupplier !== null) {
        $this->setDefaultSupplier($event->product, $defaultSupplier);
      }
      

      // Бренды и категории у модификаций не могут отличаться от тех, которые у базового товара
      // Поэтому присваивает автоматически
      if(\Store::isModVertical()) {
        $this->handleBrandAndCategory($event->product);
      }      

      if(\Store::isModHorizontall()) {
        $this->handleHorizontallModifications($event);
      }


      // Product properties
      if($event->product->props) {
        $this->handleAttributes($event);
      }


      CatalogSyncTouch::touch((int) $event->product->id);
    }


    private function handleAttributes($event) {

      foreach($event->product->props as $prop_id => $prop_value) {
        $attribute = Attribute::find($prop_id);

        if($attribute->type === 'checkbox') {

          // Delete all detached values
          AttributeProduct::where('product_id', $event->product->id)
                            ->where('attribute_id', $prop_id)
                            ->whereNotIn('attribute_value_id', $prop_value)
                            ->delete();
          
          // Attach all values
          foreach($prop_value as $attribute_value_id) {
            AttributeProduct::firstOrCreate(
              ['product_id' => $event->product->id, 'attribute_id' => $prop_id, 'attribute_value_id' => (int)$attribute_value_id]
            );
          }
        }elseif($attribute->type === 'radio') {
          // Delete record if is empty value
          if(empty($prop_value)) {
            AttributeProduct::where('product_id', $event->product->id)->where('attribute_id', $prop_id)->delete();
          }else {
            AttributeProduct::updateOrCreate(
              ['product_id' => $event->product->id, 'attribute_id' => $prop_id],
              ['attribute_value_id' => $prop_value]
            );
          }
        }elseif($attribute->type === 'number') {
          
          // Delete record if is empty value
          if(empty($prop_value)) {
            AttributeProduct::where('product_id', $event->product->id)->where('attribute_id', $prop_id)->delete();
          }else {
            AttributeProduct::updateOrCreate(
              ['product_id' => $event->product->id, 'attribute_id' => $prop_id],
              ['value' => $prop_value]
            );
          }
        }elseif($attribute->type === 'string') {
          
          // Delete record if is empty value
          if(empty($prop_value)) {
            AttributeProduct::where('product_id', $event->product->id)->where('attribute_id', $prop_id)->delete();
          }else {

            AttributeProduct::updateOrCreate(
              ['product_id' => $event->product->id, 'attribute_id' => $prop_id],
              ['value_trans' => $prop_value]
            );
          }
        }
      }
    }



    private function handleBrandAndCategory($product) {
      $parent = $product->parent;

      if(!$parent) return;

      // brand
      $product->brand_id = $parent->brand_id;

      // categories
      $product->categories()->sync($parent->categories->pluck('id'));
    }
    
    private function handleHorizontallModifications($event) {
        if (!empty($event->product->skipServiceModificationSync)) {
          return;
        }
        // Save modifications
        $modifications = $event->product->modificationsToSave;
        $old_modifications = $event->product->modifications;

        if(!empty($modifications) && is_array($modifications)) {
          UpdateProductModificationsHorizontall::dispatch($modifications, $event->product);
        }elseif(!empty($old_modifications) && empty($modifications)){
          RemoveAllProductModificationsHorizontall::dispatch($event->product);
        }  
    }
    
    /**
     * setMultipleSuppliers
     *
     * @param  mixed $product
     * @param  mixed $suppliers
     * @return void
     */
    private function setMultipleSuppliers($product, $suppliers){
      $suppliers_pivot_data = [];

      foreach($suppliers as $key => $supplier) {
        $supplier_id = $supplier['supplier'];

        $suppliers_pivot_data[$supplier_id] = $this->createSuppliersPivotData($supplier);
      }

      // Устанавливаем связь с Supplier через SupplierProduct
      $product->syncSuppliers($suppliers_pivot_data);
    }
    
    private function createSuppliersPivotData($supplier) {
      return [
        'is_active' => (isset($supplier['is_active']) && !empty($supplier['is_active']))? $supplier['is_active']: false,
        'code' => (isset($supplier['code']) && !empty($supplier['code']))? $supplier['code']: null,
        'barcode' => (isset($supplier['barcode']) && !empty($supplier['barcode']))? $supplier['barcode']: null,
        'in_stock' => (isset($supplier['in_stock']) && !empty($supplier['in_stock']))? intval($supplier['in_stock']): 0,
        'price' => (isset($supplier['price']) && !empty($supplier['price']))? doubleval($supplier['price']): null,
        'old_price' => (isset($supplier['old_price']) && !empty($supplier['old_price']))? doubleval($supplier['old_price']): null,
      ];
    }

    
    /**
     * setDefaultSupplier
     *
     * @param  mixed $product
     * @param  mixed $supplier
     * @return void
     */
    private function setDefaultSupplier($product, $supplier) {
      $data = [
        'supplier_id' => null,
        'is_active' => (isset($supplier['is_active']) && !empty($supplier['is_active']))? $supplier['is_active']: false,
        'code' => (isset($supplier['code']) && !empty($supplier['code']))? $supplier['code']: null,
        'barcode' => (isset($supplier['barcode']) && !empty($supplier['barcode']))? $supplier['barcode']: null,
        'in_stock' => (isset($supplier['in_stock']) && !empty($supplier['in_stock']))? intval($supplier['in_stock']): 0,
        'price' => (isset($supplier['price']) && !empty($supplier['price']))? doubleval($supplier['price']): null,
        'old_price' => (isset($supplier['old_price']) && !empty($supplier['old_price']))? doubleval($supplier['old_price']): null,
      ];

      $sp = SupplierProduct::updateOrCreate(
        ['product_id' => $product->id],
        $data
      );
    }
}
