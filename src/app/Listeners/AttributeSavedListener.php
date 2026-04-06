<?php
 namespace Backpack\Store\app\Listeners;
 
use Illuminate\Http\Request;
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
      $lang = backpack_translatable_request_locale(config('app.locale'));

      // Attach attributes that is presented
      // $values = AttributeValue::whereIn('id', $event->attribute->values_store)
      //                           ->update(['attribute_id' => $event->attribute->id]);

      $values = $event->attribute->attribute_values;
      
      if(empty($values) || !is_array($values))
        return;

      $processed_attribute_value_ids = [];

      for($i = 0; $i < count($values); $i++){
        $item = $values[$i];
        $slug = $item['slug'] ?? null;

        if(!empty($item['id'])) {
          // update exists
          $av = AttributeValue::find($item['id']);
          
          if(!$av) {
            continue;
          }

          $av->setTranslation('value', $lang, $item['value']);
          $av->transform = !empty($item['transform'])? $item['transform']: null;

          $extras = is_array($av->extras) ? $av->extras : [];

          if(!empty($item['transform_value'])) {
            $extras['transform_value'] = $item['transform_value'];
          } else {
            unset($extras['transform_value']);
          }

          $av->extras = !empty($extras) ? $extras : null;
          $av->syncSlug($slug);
          $av->save();
        }else {
          // create new
          $av = new AttributeValue();
          $av->attribute_id = $event->attribute->id;
          $av->setTranslation('value', $lang, $item['value']);
          $av->transform = !empty($item['transform'])? $item['transform']: null;

          $extras = [];
          if(!empty($item['transform_value'])) {
            $extras['transform_value'] = $item['transform_value'];
          }

          $av->extras = !empty($extras) ? $extras : null;
          $av->syncSlug($slug);
          $av->save();

          $processed_attribute_value_ids[] = $av->id;
        }

        if(!empty($item['id'])) {
          $processed_attribute_value_ids[] = $item['id'];
        }
      }

      try{
        // Detach attributes that not presented
        $event->attribute->values()->whereNotIn('id', $processed_attribute_value_ids)->delete();
      }catch(\Exception $e) {
        \Alert::add('error', 'Не удалось удалить значение так как оно привязано к одному или более товаров.');
      }
    }
}
