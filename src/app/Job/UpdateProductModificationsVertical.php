<?php

namespace Backpack\Store\app\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;

use Backpack\Store\app\Models\Product;
 
use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;

class UpdateProductModificationsVertical implements ShouldQueue, ShouldBeUnique
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
        // Если у родительского базового товара меняются бренд или категории, у всех детей должны быть установленны значения заново 
        if($this->product->children()->exists()) {
          // brand
          $this->product->children()->update(['brand_id' => $this->product->brand_id]);

          // categories
          $categoryIds = $this->product->categories()->pluck('ak_product_categories.id')->toArray();
          $this->product->children()->each(function ($child) use ($categoryIds) {
              $child->categories()->sync($categoryIds);

              CatalogSyncTouch::touch((int) $child->id);
          });
        }
    }
    
}
