<?php
 namespace Backpack\Store\app\Listeners;
 
use Backpack\Store\app\Events\AttributeSaved;

use Backpack\Store\app\Models\AttributeValue;
 
class AttributeSavedListener
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
     * @param  \App\Events\OrderShipped  $event
     * @return void
     */
    public function handle(AttributeSaved $event)
    {
      // Detach attributes that not presented
      $event->attribute->values()->whereNotIn('id', $event->attribute->values_store)->delete();
      
      // Attach attributes that is presented
      $values = AttributeValue::whereIn('id', $event->attribute->values_store)
                                ->update(['attribute_id' => $event->attribute->id]);
    }
}