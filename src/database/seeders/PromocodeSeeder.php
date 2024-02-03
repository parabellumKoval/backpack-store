<?php

namespace Backpack\Store\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

use Backpack\Store\app\Models\Promocode;

class PromocodeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {   
      $this->createPromocodes();
    }

    private function createPromocodes() {
      Promocode::factory()
          ->count(10)
          ->create();
    }
}
