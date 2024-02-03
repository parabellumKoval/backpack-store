<?php
 
namespace Backpack\Store\tests\Unit;
 
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Promocode;

// DATE
use Carbon\Carbon;
 
class PromocodeTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        Promocode::truncate();
    }
        
    /**
     * test_scope_valid
     * 
     * Test scope valid is ok when ok
     *
     * @return void
     */
    public function test_scope_valid_return_all_valid_items(): void
    {
      Promocode::factory()->state([
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->count(5)->create();

      $promocodes = Promocode::valid()->get();
      $this->assertTrue($promocodes->count() === 5);
    }

    public function test_scope_valid_does_not_return_invalid_items(): void
    {
      // Invalid by is_active field
      Promocode::factory()->state([
        'is_active' => 0,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->count(1)->create();

      // Invalid by limit/used_times fields
      Promocode::factory()->state([
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 10,
        'valid_until' => Carbon::now()->addWeek()
      ])->count(1)->create();

      // Invalid by valid_until field
      Promocode::factory()->state([
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 1,
        'valid_until' => Carbon::now()->subDays(5)
      ])->count(1)->create();

      // Invalid by all fields
      Promocode::factory()->state([
        'is_active' => 0,
        'limit' => 10,
        'used_times' => 12,
        'valid_until' => Carbon::now()->subDays(1)
      ])->count(1)->create();

      $promocodes = Promocode::valid()->get();

      $this->assertTrue($promocodes->count() === 0);
    }

    public function test_is_valid_attribute(): void {
      $promocode = Promocode::factory()->state([
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->create();
      
      $this->assertTrue($promocode->isValid);
    }
}