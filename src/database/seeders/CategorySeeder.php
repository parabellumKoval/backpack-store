<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

      Category::where('id', '>=', 0)->delete();
      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>All Categories was deleted.</info>");
      
      $this->createCategoriesTree();
    }

    
    private function createCategoriesTree($category = null, $itteration = 1) {

      $itterations_limit = config('backpack.store.category.depth_level', 1);

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
