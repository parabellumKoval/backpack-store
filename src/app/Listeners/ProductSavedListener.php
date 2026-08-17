<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

use Backpack\Store\app\Job\UpdateProductModifications;
use Backpack\Store\app\Job\RemoveAllProductModifications;
 
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
      $suppliers = $event->product->suppliers_data ?? $event->product->default_supplier ?? null;
      if(!empty($suppliers)) {
        if(config('backpack.store.supplier.enable', false)) {
          $this->setMultipleSuppliers($event->product, $suppliers);
        }else {
          $this->setDefaultSupplier($event->product, $suppliers);
        }
      }
      

      if(config('backpack.store.product.modifications.enable', true)) {

        // Save modifications
        // NOTE: run these synchronously (dispatchSync) instead of pushing them onto the queue.
        // Pushing tiny parent_id updates onto the queue made the "Related products" field
        // silently stay empty after save whenever the queue worker was not processing this
        // connection (or a stale ShouldBeUnique lock dropped the job). The work is 1-2 UPDATE
        // queries, so doing it inline during the request is both reliable and cheap.
        // $modificationsToSave is only populated when the "modifications" field is actually
        // present in the submitted form (via setModificationsAttribute). An empty multiselect
        // submits no key at all, so we deliberately DO NOT remove existing relations here —
        // that keeps unrelated saves (e.g. XML imports, editing other fields) from wiping the
        // related-products relation.
        $modifications = $event->product->modificationsToSave;

        if(!empty($modifications) && is_array($modifications)) {
          UpdateProductModifications::dispatchSync($modifications, $event->product);
        }
      }


      if(!$event->product->props)
        return;

      // Product properties
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

    
    /**
     * setMultipleSuppliers
     *
     * @param  mixed $product
     * @param  mixed $suppliers
     * @return void
     */
    private function setMultipleSuppliers($product, $suppliers){
      $sync_pivot_data = [];

      foreach($suppliers as $key => $supplier) {
        $supplier_id = $supplier['supplier'];

        $sync_pivot_data[$supplier_id] = [
          'code' => (isset($supplier['code']) && !empty($supplier['code']))? $supplier['code']: null,
          'barcode' => (isset($supplier['barcode']) && !empty($supplier['barcode']))? $supplier['barcode']: null,
          'in_stock' => (isset($supplier['in_stock']) && !empty($supplier['in_stock']))? intval($supplier['in_stock']): 0,
          'price' => (isset($supplier['price']) && !empty($supplier['price']))? doubleval($supplier['price']): null,
          'old_price' => (isset($supplier['old_price']) && !empty($supplier['old_price']))? doubleval($supplier['old_price']): null,
        ];
        
      }

      $product->syncSuppliers($sync_pivot_data);
      // $product->suppliers()->sync($sync_pivot_data);
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