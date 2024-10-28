<?php
namespace Backpack\Store\app\Console\Commands;

use Illuminate\Console\Command;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\AttributeValue;
use Backpack\Store\app\Models\AttributeProduct;

class AttributesTransform extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ak_store:attributes-transform';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    protected $available_languages;
    protected $langs_list;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
      parent::__construct();

      // languages
      $this->available_languages = config('backpack.crud.locales');
      $this->langs_list = array_keys($this->available_languages);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
      $avs = AttributeValue::whereNotNull('transform')->get();

      $bar = $this->output->createProgressBar(count($avs));
			$bar->start();

      foreach($avs as $av) {

        if($av->transform === 'join') {
          $this->joinAttributeValues($av);
        }else if($av->transform === 'split') {
          $this->splitAttributeValues($av);
        }

        // $av->transform = null;
        // $extras = $av->extras;
        // $extras['transform_value'] = null;
        // $av->extras = $extras;
        // $av->save();

      	$bar->advance();
      }

			$bar->finish();
    }
  
    
    /**
     * joinAttributeValues
     *
     * @param  mixed $av
     * @return void
     */
    private function joinAttributeValues($av) {
      $join_with = $av->transformValue;

      if(empty($join_with)) {
        return;
      }

      $langs_list = $this->langs_list;

      // Find new attribute_value_id
      $join_with_av = AttributeValue::where('attribute_id', $av->attribute_id)
                      ->where('id', '!=', $av->id)
                      ->where(function($query) use ($join_with, $langs_list){
                        foreach($langs_list as $index => $lang_key) {
                          $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                          $query->{$function_name}('LOWER(JSON_EXTRACT(value, "$.' . $lang_key . '")) = ? ', ['"' . trim(mb_strtolower($join_with)) . '"']);
                        }
                      })
                      ->first();
    
      if(!$join_with_av) {
        $this->error('value was not found: ' . $join_with);
        return;
      }
      
      // Change attribute products to new attribute_value id
      $aps = AttributeProduct::where('attribute_id', $av->attribute_id)
                              ->where('attribute_value_id', $av->id)
                              ->update([
                                'attribute_value_id' => $join_with_av->id
                              ]);
      
      // Remove old attribute_value
      $av->delete();
    }
    
    /**
     * splitAttributeValues
     *
     * @param  mixed $av
     * @return void
     */
    private function splitAttributeValues($av) {
      if(empty($av->transformValue)) {
        return;
      }

      $split_to = explode('|', $av->transformValue);

      if(empty($split_to) || !is_array($split_to)){
        $this->error('value was not split because it is not array');
        return;
      }

      $langs_list = $this->langs_list;

      // old attribute products
      $aps = AttributeProduct::where('attribute_id', $av->attribute_id)
                        ->where('attribute_value_id', $av->id)
                        ->get();

      foreach($split_to as $item) {
        if(empty($item)) {
          continue;
        }

        // Find new attribute_value_id
        $new_av = AttributeValue::where('attribute_id', $av->attribute_id)
                      ->where('id', '!=', $av->id)
                      ->where(function($query) use ($item, $langs_list){
                        foreach($langs_list as $index => $lang_key) {
                          $function_name = $index === 0? 'whereRaw': 'orWhereRaw';
                          $query->{$function_name}('LOWER(JSON_EXTRACT(value, "$.' . $lang_key . '")) = ? ', ['"' . trim(mb_strtolower($item)) . '"']);
                        }
                      })
                      ->first();

        // create new attribute_value  
        if(!$new_av) {
          $new_av = new AttributeValue();
          $new_av->attribute_id = $av->attribute_id;
          $new_av->value = $item;
          $new_av->save();
        }

        // Attach new attribute value to products
        foreach($aps as $ap) {
          AttributeProduct::create([
            'product_id' => $ap->product_id,
            'attribute_id' => $av->attribute_id,
            'attribute_value_id' => $new_av->id,
          ]);
        }
      }

      // Remove old Attribute product
      if($aps->count()) {
        foreach($aps as $ap) {
          $ap->delete();
        }
      }

      // Remove old attribute_value
      $av->delete();
    }
  
}
