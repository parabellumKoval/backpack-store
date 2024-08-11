<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Product;
 
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
      

      if(config('backpack.store.product.modifications.enable', true)) {

        // Reset old relations
        Product::where('parent_id', $event->product->parent_id)
          ->orWhere('parent_id', $event->product->id)
          ->update([
            'parent_id' => null
          ]);

        // Save modifications
        $mods = $event->product->modificationsToSave;

        if(!empty($mods) && is_array($mods)) {
          // Set new Relations
          Product::whereIn('id', $mods)->update([
            'parent_id' => $event->product->id
          ]);
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
}