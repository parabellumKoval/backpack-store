<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\Brand;

class ProductSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

      Product::where('id', '>=', 0)->delete();
      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>Product was deleted.</info>");

      $this->createProducts();
    }

    private function createProducts() {
      $categories = Category::all();

      // create childrens
      $childrens_count = config('backpack.store.modifications.enable', false)? 2: 0;

      foreach($categories as $category) {
        $brand = null;
  
        if(config('backpack.store.brands.enable', false)) {
          $brand = Brand::inRandomOrder()->first();
        }
        
        $products = Product::factory()
          ->count(3)
          ->hasAttached($category)
          ->hasChildren($childrens_count)
          ->state([
            'brand_id' => ($brand->id ?? null)
          ])
          ->suspended()
          ->create();
      }
    }
}
