<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;

class OrderSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {   
      $this->createOrders();
    }

    private function createOrders() {

      // Create 20 orders
      for($i = 0; $i < 20; $i++) {

        $products = Product::inRandomOrder()->where('is_active', 1)->limit(3)->get();
        $product_ids = $products->pluck('id')->toArray();
        $products_to_attach = [];

        for($p = 0; $p < count($product_ids); $p++){
          $id = $product_ids[$p];
          $products_to_attach[$id] = [
            'amount' => rand(1,10),
            'value' => rand(100, 9999)  
          ];
        }

        $order = Order::factory()
            ->create();

        $order->products()->attach($products_to_attach);
      }
    }
}
