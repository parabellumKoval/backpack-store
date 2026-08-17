<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;

use Backpack\Store\app\Models\Product;

class UpdateProductModifications implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
				
    private $modifications = null;
    private $product = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($modifications, $product)
    {
      $this->modifications = $modifications;
      $this->product = $product;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      $this_id = $this->product->id;

      // Detach the products that are currently attached to THIS product as modifications.
      Product::where('parent_id', $this_id)->update([
        'parent_id' => null
      ]);

      // A product can never be its own modification.
      $modifications = array_filter($this->modifications, function($id) use($this_id) {
        return $id != $this_id;
      });

      // Attach the newly selected products as modifications of this product.
      if(!empty($modifications)) {
        Product::whereIn('id', $modifications)->update([
          'parent_id' => $this_id
        ]);
      }
    }
    
}
