<?php
 
namespace Backpack\Store\tests\Unit;
 
use Backpack\Store\Tests\TestCase;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Promocode;

// DATE
use Carbon\Carbon;
 
class OrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Promocode::truncate();
    }
        
    /**
     * test_usePromocode_type_value
     * 
     * Test promocode with type = value
     *
     * @return void
     */
    public function test_usePromocode_type_value(): void
    {
      $sale_value = 100;

      // Valid promocode
      $promocode = Promocode::factory()->state([
        'value' => $sale_value,
        'type' => 'value',
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->create();

      $order = Order::factory()->suspended()->create();

      // Save base order price
      $base_order_price = $order->price;

      $order->usePromocode($promocode->code);
      $order->save();

      $this->assertTrue(($base_order_price - $order->price) == $sale_value);
    }

     /**
     * test_usePromocode_type_percent
     * 
     * Test promocode with type = percent
     *
     * @return void
     */
    public function test_usePromocode_type_percent(): void
    {
      $sale_value = 50;

      // Valid promocode
      $promocode = Promocode::factory()->state([
        'value' => $sale_value,
        'type' => 'percent',
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->create();

      $order = Order::factory()->suspended()->create();

      // Save base order price
      $base_order_price = $order->price;

      $order->usePromocode($promocode->code);
      $order->save();

      $this->assertTrue(round(($base_order_price / 2), 2) === $order->price);
    }
    
    /**
     * test_usePromocode_invalid_promocode
     *
     * Not valid promocode should throw Exception with 401 code
     * 
     * @return void
     */
    public function test_usePromocode_invalid_promocode():void {
      // Invalid promocode
      $promocode = Promocode::factory()->state([
        'is_active' => 0,
        'limit' => 10,
        'used_times' => 10,
        'valid_until' => Carbon::now()
      ])->create();

      $order = Order::factory()->suspended()->create();

      // Save base order price
      $base_order_price = $order->price;

      // Expect that throw Exception with 401 status
      $this->expectException(\Exception::class);

      $order->usePromocode($promocode->code);

    }
    
    /**
     * test_usePromocode_non_existent_promocode
     *
     * Not existed promocode should throw Exception with 404 code
     * 
     * @return void
     */
    public function test_usePromocode_non_existent_promocode():void {
      $order = Order::factory()->suspended()->create();

      // Save base order price
      $base_order_price = $order->price;

      // Expect that throw Exception with 401 status
      $this->expectException(\Exception::class);

      // Fail promocode
      $order->usePromocode('111111');
    }
    
    /**
     * test_promocode_attribute
     * 
     * Test promocode attribute return correct data
     *
     * @return void
     */
    public function test_promocode_attribute():void
    {
      // Valid promocode
      $promocode = Promocode::factory()->state([
        'is_active' => 1,
        'limit' => 10,
        'used_times' => 5,
        'valid_until' => Carbon::now()->addWeek()
      ])->create();

      // create order
      $order = Order::factory()->suspended()->create();

      // apply promocode
      $order->usePromocode($promocode->code);
      $order->save();

      $this->assertSame($promocode->toArray(), $order->promocode);
    }
    
    /**
     * test_user_attribute
     *
     * @return void
     */
    public function test_user_attribute():void {
      // create order
      $order = Order::factory()->suspended()->create();

      // 1.
      $this->assertIsArray($order->user);

      // 2.
      $this->assertTrue(count($order->user) > 0);
    }

    
    /**
     * test_delivery_attribute
     *
     * @return void
     */
    public function test_delivery_attribute():void {
      // create order
      $order = Order::factory()->suspended()->create();

      // 1.
      $this->assertIsArray($order->delivery);

      // 2.
      $this->assertTrue(count($order->delivery) > 0);
    }

    
    /**
     * test_payment_attribute
     *
     * @return void
     */
    public function test_payment_attribute():void {
      // create order
      $order = Order::factory()->suspended()->create();

      // 1.
      $this->assertIsArray($order->payment);

      // 2.
      $this->assertTrue(count($order->payment) > 0);
    }
    
    /**
     * test_products_anyway_attributes
     * 
     * This attribule should return products array:
     *  -- from info JSON
     *  -- from relationship 
     *  -- empty array otherwise
     *
     * @return void
     */
    public function test_products_anyway_attributes():void {
      // create order with only products data inside info JSON field
      $order_without_relations = Order::factory()->create();

      // No products relations
      $this->assertTrue($order_without_relations->products->count() === 0);
      // But 
      $this->assertTrue(count($order_without_relations->productsAnyway) > 0);
      // Responce is Array
      $this->assertIsArray($order_without_relations->productsAnyway);

      // create order with only products ralationship
      $order_without_info = Order::factory()
      ->state(function($attribute){
        $info = $attribute['info'];
        $info['products'] = null;

        return [
          'info' => $info
        ];
      })
      ->hasAttached(
        Product::factory()->count(3),
        [
          'amount' => rand(1,10),
          'value' => rand(100, 9999)
        ]
      )->create();

      // No data about products inside info JSON
      $this->assertTrue(!$order_without_info->info['products']);
      // But 
      $this->assertTrue(count($order_without_info->productsAnyway) > 0);
      // Responce is Array
      $this->assertIsArray($order_without_relations->productsAnyway);

      // create order without products
      $order_without_info = Order::factory()
      ->state(function($attribute){
        $info = $attribute['info'];
        $info['products'] = null;

        return [
          'info' => $info
        ];
      })->create();

      $this->assertTrue(count($order_without_info->productsAnyway) === 0);
      // Responce is Array
      $this->assertIsArray($order_without_relations->productsAnyway);
    }
 }