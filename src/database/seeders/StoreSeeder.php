<?php
namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;

use Backpack\Store\database\seeders\CategorySeeder;
use Backpack\Store\database\seeders\ProductSeeder;
use Backpack\Store\database\seeders\AttributeSeeder;
use Backpack\Store\database\seeders\OrderSeeder;
use Backpack\Store\database\seeders\PromocodeSeeder;
use Backpack\Store\database\seeders\BrandSeeder;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;

class StoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      // Remove all orders first because foreign key
      Order::where('id', '>=', 0)->delete();
      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>Order was deleted.</info>");

      // Then remove products
      Product::where('id', '>=', 0)->delete();
      (new \Symfony\Component\Console\Output\ConsoleOutput())->writeln("<info>Product was deleted.</info>");

      if(config('backpack.store.brands.enable', false)) {
        (new BrandSeeder())->run();
      }

      (new CategorySeeder())->run();
      (new ProductSeeder())->run();
      (new AttributeSeeder())->run();
      (new OrderSeeder())->run();
      (new PromocodeSeeder())->run();
    }
    
}
