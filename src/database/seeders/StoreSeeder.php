<?php
namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;

use Backpack\Store\database\seeders\CategorySeeder;
use Backpack\Store\database\seeders\ProductSeeder;
use Backpack\Store\database\seeders\AttributeSeeder;
use Backpack\Store\database\seeders\OrderSeeder;
use Backpack\Store\database\seeders\PromocodeSeeder;

class StoreSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
      (new CategorySeeder())->run();
      (new ProductSeeder())->run();
      (new AttributeSeeder())->run();
      (new OrderSeeder())->run();
      (new PromocodeSeeder())->run();
    }
    
}
