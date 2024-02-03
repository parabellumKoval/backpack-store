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
      $this->createAttributes();
    }

    private function createAttributes() {
      $attributes = Attribute::factory()
              ->count(10)
              ->suspended()
              ->create();
    }

    private function createProducts() {
      $categories = Category::all();

      foreach($categories as $category) {
        $childProducts = Product::factory()->count(3)->hasAttached($category)->create();

        $products = Product::factory()
            ->count(3)
            ->hasAttached($category)
            ->hasChildren($childProducts)
            // ->hasAttrs($attributes)
            ->suspended()
            ->create();

        // Attach attributes
        foreach($products as $product) {
          $attributes = Attribute::inRandomOrder()->limit(3)->get();
          $attrs_array = [];

          foreach($attributes as $attribute) {
            if($attribute->type === 'checkout' || $attribute->type === 'radio'){
              $value = $attribute->values[array_rand($attribute->values)];
            }else {
              $value = 100;
            }

            $attrs_array[$attribute->id] = [
              'value' => $value
            ];
          }

          $product->attrs()->attach($attrs_array);
        }
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
