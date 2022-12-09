<?php

namespace Aimix\Shop\app\Observers;

use Aimix\Shop\app\Models\Product;
use Aimix\Shop\app\Models\Modification;

class ProductObserver
{
    private $product;
    
    // public function created(Product $product){
    //   $this->product = $product;
      
    //   if(!$product->wasSaved){
    //     $product->wasSaved = true;
    //     $this->updateOrCreateModification($product->modifications_array);
    //   }
    // }
    
    public function saved(Product $product){
	    $product->modifications_array =  $product->modifications_array?: request()->input('mod');
	    $product->images_array = $product->images_array?: request()->input('images');
	    
	   //dd($product->images_array);
      $this->product = $product;
      
      // if($product->images_array)
      //   dd($product->images_array);
      
      if(!$product->isModificationRelation && $product->modifications_array){
        
        if($product->modifications_array)
          $product->modifications_array[0]['images'] = $product->images_array;
        
          $this->updateOrCreateModification($product->modifications_array);
          
      }
      
      \Artisan::call('optimize');
    }
    
    public function updateOrCreateModification($modifications) {
	   // dd($modifications);
      foreach($modifications as $modification){
	     // dd($modification);
/*
        $this->product->modifications()->updateOrCreate([
          'id' => $modification['id'],
        ], $modification);
*/
		$mod = $this->product->modifications()->find($modification['id']);
		
		if(!$mod){
			$mod = new Modification();
			$mod->product_id = $this->product->id;
		}
	      foreach($modification as $key => $value)
		      $mod->{$key} = $value;
	      
	      $mod->save();

      }
    }
    
    public function deleting(Product $product) {
      foreach($product->modifications as $modification){
        $modification->attrs()->detach();
      }
      
      $product->modifications()->delete();
    }
}
