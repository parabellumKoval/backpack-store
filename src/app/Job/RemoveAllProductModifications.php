<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use Backpack\Store\app\Models\Product;

class RemoveAllProductModifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
				
    private $product = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($product)
    {
      $this->product = $product;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      // Reset old relations
      Product::where('parent_id', $this->product->parent_id)
        ->orWhere('parent_id', $this->product->id)
        ->update([
          'parent_id' => null
        ]);
    }
    
}
