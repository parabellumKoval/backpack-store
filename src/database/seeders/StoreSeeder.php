<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
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

      // foreach($categories as $category){
      //   $this->createCategoriesTree($category, 1);
      // }
        //$categories = Category::factory(3)->create();

        //config('backpack.store.category_depth_level')

        // foreach($categories as $category){
        //   Product::factory()
        //     ->count(3)
        //     ->for($category)
        //     ->suspended()
        //     ->create();
        // }
        


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
            // ->state([
            //   'parent_id' => Product::where('category_id', $category->id)->inRandomOrder()->first(),
            // ])
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
