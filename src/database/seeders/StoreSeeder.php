<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Attribute;

class StoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

      $this->createCategoriesTree();

      $this->createProducts();
        
      $this->createOrders();
    }

    private function createOrders() {
      Order::factory()
          ->count(10)
          ->hasAttached(
            Product::factory()->count(3),
            [
              'amount' => rand(1,10),
              'value' => rand(100, 9999)
            ]
          )
          ->create();
    }

    private function createProducts() {
      $categories = Category::all();

      foreach($categories as $category) {
        Product::factory()
            ->count(3)
            ->for($category)
            ->has(Product::factory()->count(3)->state(function (array $attributes, Product $product){
              return ['category_id' => $product->category->id];
            }), 'children')
            ->suspended()
            ->create();
      }
    }

    private function createCategoriesTree($category = null, $itteration = 1) {

      $itterations_limit = config('backpack.store.category_depth_level', 1);

      if($itteration > $itterations_limit) {
        return;
      }

      if(!$category) {
        $categories = Category::factory(3)->create();
      }else {
        $categories = Category::factory()
                ->count(3)
                ->state([
                  'parent_id' => $category->id,
                ])
                ->create();
      }

      foreach($categories as $new_category){
        $this->createCategoriesTree($new_category, $itteration + 1);
      }

      return null;
    }
}
