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
}
