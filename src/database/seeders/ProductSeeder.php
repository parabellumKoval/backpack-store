<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;

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

      foreach($categories as $category) {
        $products = Product::factory()
          ->count(3)
          ->hasAttached($category)
          ->hasChildren(2)
          ->suspended()
          ->create();
      }
    }
}
