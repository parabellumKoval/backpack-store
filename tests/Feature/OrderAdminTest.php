<?php

namespace Backpack\Store\tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Order;
use Backpack\Store\database\seeders\OrderSeeder;

class OrderAdminTest extends TestCase
{
  // use WithoutMiddleware;
  // use RefreshDatabase;

  protected function setUp(): void {
    parent::setUp();

    // Run order seeder
    $this->seed(OrderSeeder::class);
  }

  public function test_order_index_200() {
    $response = $this->get('/admin/order'); 
    $response->assertStatus(200);
  }

  public function test_order_edit_200 () {
    $order = Order::firstOrFail();
    $response = $this->get('/admin/order/'.$order->id.'/edit');
    $response->assertStatus(200);
  }

  public function test_order_show_200 () {
    $order = Order::firstOrFail();
    $response = $this->get('/admin/order/'.$order->id.'/show');
    $response->assertStatus(200);
  }

  public function test_order_create_200 () {
    $response = $this->get('/admin/order/create');
    $response->assertStatus(200);
  }
}
