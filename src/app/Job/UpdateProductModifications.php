<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Backpack\Store\app\Models\Product;

class UpdateProductModifications implements ShouldQueue
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

      // Reset old relations
      Product::where('parent_id', $this->product->parent_id)
        ->orWhere('parent_id', $this->product->id)
        ->update([
          'parent_id' => null
        ]);

      $modifications = array_filter($this->modifications, function($id) use($this_id) {
        return $id != $this_id;
      });

      // Set new Relations
      Product::whereIn('id', $modifications)->update([
        'parent_id' => $this->product->id
      ]);
    }
    
}
