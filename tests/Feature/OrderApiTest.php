<?php

namespace Backpack\Store\Tests\Feature;

use Illuminate\Support\Facades\Auth;

use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Backpack\Store\Tests\TestCase;

// DATE
use Carbon\Carbon;

use Backpack\Store\database\seeders\OrderSeeder;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Promocode;

class OrderApiTest extends TestCase
{
  protected $order_data = [
      'products' => [],
      'provider' => 'auth',
      'payment' => [
        'method' => 'cash'
      ],
      'delivery' => [
        'method' => 'address',
        'city' => 'City',
        'address' => 'Long address string',
        'zip' => '61000',
      ]
    ];
    
  /**
   * getOrderData
   *
   * @return array $this->order_data
   */
  protected function getOrderData() {
    $products_ids_array = Product::take(3)->pluck('id')->toArray();
    $products_amount_array = array_map(fn($item) => rand(1, 10), array_flip($products_ids_array));

    // Fill order data correct products data
    $this->order_data['products'] = $products_amount_array;

    return $this->order_data;
  }

    protected function setUp(): void {
      parent::setUp();

      $this->seed(OrderSeeder::class);

      // Set user to session
      Auth::guard('profile')->login($this->user);

      // $this->actingAs($this->user);
    }

     /**
     * test_index_is_200
     * 
     * Clearly test api index route is 200 status
     *
     * @return void
     */
    public function test_index_is_200()
    {
        $response = $this->get('/api/orders/all');
        $response->assertStatus(200);
    }

    /**
     * test_orders_isset_in_db
     * 
     * Test orders exists in DB after seeding and 
     * return by api index route without exceptions and fails
     *
     * @return void
     */
    public function test_orders_isset_in_db() 
    {
      $response = $this->getJson('/api/orders/all');
      
      try {
        $response_array = $response->json();
      }catch(\Exception $e) {
        echo 'Error duaring json getting: ' . $e->getMessage(); 
      }

      $this->assertTrue(count($response_array['data']) > 0);
    }

     /**
     * test_index_structure_is_ok
     * 
     * Test whole clear index api route return correct json structure 
     *
     * @return void
     */
    public function test_index_structure_is_ok()
    {
      $response = $this->get('/api/orders/all'); 
      $response->assertJsonStructure([
        'data' => [
          '*' => [
            'id',
            'code',
            'price',
            'status',
            'payStatus',
            'deliveryStatus',
            'user' => [
              'firstname',
              'lastname',
              'phone',
              'email'
            ],
            'delivery' => [
              "zip",
              "room",
              "house",
              "method",
              "street",
              "city",
              "warehouse",
              "comment"
            ],
            "payment" => [
              "method"
            ],
            "products" => [
              "*" => [
                "name",
                "slug",
                "amount",
                "price",
                "old_price",
                "image"
              ]
            ],
            "created_at"
          ]
        ],
        'meta' => [
          'current_page',
          'from',
          'last_page',
          'links',
          'path',
          'per_page',
          'to',
          'total'
        ]
      ]);
    }


  /**
   * test_create_ok
   * 
   * Test if order created successfully.
   *
   * @return void
   */
  public function test_create_ok () {
    $response = $this->post('/api/orders', $this->getOrderData());
    $response->assertStatus(200);
  }
    
  /**
   * test_create_request_validation_error
   * 
   * Test error handling if empty data during order creation 
   *
   * @return void
   */
  public function test_create_request_validation_error() {
    $response = $this->post('/api/orders', []);
    $response->assertStatus(403);
  }

  /**
   * test_create_wrong_products_array_error
   * 
   * Test error handling when products data is wrong
   *
   * @return void
   */
  public function test_create_wrong_products_array_error() {
    // Wrong product ids [product_id => product_amount]
    $products_amount_array = [
      32424 => 1,
      555542 => 1,
    ];

    $this->order_data['products'] = $products_amount_array;

    $response = $this->post('/api/orders', $this->order_data);
    $response->assertStatus(404);
  }
  
  /**
   * test_create_not_auth_user_error
   * 
   * Create order with "auth" provider, but user not authed 
   *
   * @return void
   */
  public function test_create_not_auth_user_error() {
    // User not auth
    Auth::guard('profile')->logout();

    $response = $this->post('/api/orders', $this->getOrderData());
    $response->assertUnauthorized();
  }
    
  /**
   * test_create_with_promocode
   *
   * Create order with promocode. Check if promocode works fine. 
   * 
   * @return void
   */
  public function test_create_with_promocode() {
    // Create valid promocode
    $promocode = Promocode::factory()->state([
      'is_active' => 1,
      'limit' => 10,
      'used_times' => 5,
      'valid_until' => Carbon::now()->addWeek()
    ])->create();

    $data = array_merge($this->getOrderData(), [
      'promocode' => $promocode->code
    ]);

    $response = $this->post('/api/orders', $data);
    $new_order_data = $response->json();

    $new_order_model = Order::find($new_order_data['id']);

    $this->assertNotEquals($new_order_model->getProductsPrice(), $new_order_model->price);
    // NEEDS:
    // -- check promocode data in info JSON filed

  }

  /**
   * test_order_copy_is_ok
   * 
   * Test order copy creation is ok
   *
   * @return void
   */
  public function test_order_copy_is_ok() {
    $order = Order::first();

    $response = $this->post('/api/orders/copy', ['id' => $order->id]);
    $response->assertStatus(201);
  }

  
  /**
   * test_order_copy_is_reseted
   *
   * Test order copy is reseted some fields
   * 
   * @return void
   */
  public function test_order_copy_is_reseted() {
    $base_order = Order::first();

    $response = $this->post('/api/orders/copy', ['id' => $base_order->id]);
    
    $new_order = $response->json();

    $this->assertNotEquals($base_order->code, $new_order['code']);
    $this->assertEquals($new_order['status'], config("backpack.store.order.status.default"));
    $this->assertEquals($new_order['pay_status'], config("backpack.store.order.pay_status.default"));
    $this->assertEquals($new_order['delivery_status'], config("backpack.store.order.delivery_status.default"));

    // NEEDS:
    // -- check order price
    // -- check promocode
    // -- check bonuses
  }
}
