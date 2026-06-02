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
use Backpack\Store\app\Models\OrderInvoice;
use Backpack\Store\app\Contracts\BonusService;
use Backpack\Store\app\DTO\BonusRedemption;
use Backpack\Store\app\Services\Bonus\NullBonusService;
use Backpack\Store\app\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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
    protected ?object $invoiceServiceFake = null;
    
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

    protected function tearDown(): void
    {
        app()->forgetInstance(InvoiceService::class);
        $this->invoiceServiceFake = null;

        parent::tearDown();
    }

    protected function bindFakeInvoiceService(): void
    {
        $this->invoiceServiceFake = new class {
            public array $generatedOrders = [];

            public function generate(Order $order, array $options = []): array
            {
                $pdfPath = sprintf('invoices/%s.pdf', $order->getKey());
                Storage::disk('public')->put($pdfPath, 'fake-pdf');

                $invoice = $order->invoices()->create([
                    'template' => 'cz_default',
                    'locale' => 'cs_CZ',
                    'currency' => $order->currency_code ?? 'CZK',
                    'payload_hash' => 'fake-hash-' . $order->getKey(),
                    'path' => $pdfPath,
                    'filesize' => 123,
                    'generated_at' => now(),
                    'meta' => [
                        'invoice_number' => 'F' . ($order->code ?? $order->getKey()),
                        'order_number' => $order->code ?? (string) $order->getKey(),
                        'buyer' => [],
                        'issued_at' => now()->toIso8601String(),
                    ],
                ]);

                $qrPath = sprintf('invoices/qr/%s.svg', $order->getKey());
                Storage::disk('public')->put($qrPath, '<svg></svg>');

                $invoice->update([
                    'qr_format' => 'svg',
                    'qr_path' => $qrPath,
                    'qr_payload_hash' => 'qr-hash-' . $order->getKey(),
                    'qr_generated_at' => now(),
                ]);

                $this->generatedOrders[] = $order->getKey();

                return [
                    'invoice' => $invoice,
                    'binary' => 'fake-pdf',
                    'qr' => [
                        'payload' => 'fake',
                        'format' => 'svg',
                        'disk' => 'public',
                        'path' => $qrPath,
                        'hash' => 'qr-hash-' . $order->getKey(),
                        'binary' => '<svg></svg>',
                        'mime' => 'image/svg+xml',
                    ],
                ];
            }

            public function signedUrl(OrderInvoice $invoice, ?string $ttl = null): string
            {
                return URL::temporarySignedRoute(
                    'backpack.store.invoices.download-signed',
                    now()->addMinutes(15),
                    [
                        'invoice' => $invoice->getKey(),
                        'order' => $invoice->order_id,
                    ]
                );
            }
        };

        app()->instance(InvoiceService::class, $this->invoiceServiceFake);
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
        $response = $this->get('/api/order/all');
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
      $response = $this->getJson('/api/order/all');
      
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
      $response = $this->get('/api/order/all'); 
      $response->assertJsonStructure([
        'data' => [
          '*' => [
            'id',
            'code',
            'price',
            'subtotal',
            'discountTotal',
            'shippingTotal',
            'taxTotal',
            'grandTotal',
            'currencyCode',
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
            'invoiceDownloadUrl',
            'invoiceQrUrl',
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
            'bonuses' => [
              'points',
              'fiatAmount',
              'fiatCurrency',
              'walletCurrency',
              'refunded'
            ],
            'personalDiscount' => [
              'amount',
              'percent',
              'currency',
              'applied'
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
    $response = $this->post('/api/order', $this->getOrderData());
   $response->assertStatus(200);
  }

  public function test_bank_transfer_order_returns_invoice_links(): void
  {
    Storage::fake('public');

    config([
      'dress.invoice.auto_generate_payment_methods' => ['bank_transfer'],
      'app.url' => 'https://example.test',
    ]);

    $this->bindFakeInvoiceService();

    $data = $this->getOrderData();
    $data['payment'] = array_merge($data['payment'], [
      'method' => 'bank_transfer',
      'settlement' => 'Prague',
      'street' => 'Main street',
      'house' => '10A',
      'room' => '5',
      'zip' => '11000',
    ]);
    $data['delivery'] = array_merge($data['delivery'], [
      'settlement' => 'Prague',
      'street' => 'Main street',
      'house' => '10A',
      'room' => '5',
      'zip' => '11000',
    ]);

    $response = $this->postJson('/api/order', $data);
    $response->assertStatus(200);

    $payload = $response->json();

    $this->assertNotEmpty($payload['invoiceDownloadUrl'] ?? null);
    $this->assertNotEmpty($payload['invoiceQrUrl'] ?? null);

    $invoiceExists = OrderInvoice::where('order_id', $payload['id'] ?? 0)->exists();
    $this->assertTrue($invoiceExists, 'Invoice record should be created for bank transfer payment.');

    $this->assertContains($payload['id'], $this->invoiceServiceFake->generatedOrders);
  }

  public function test_non_invoice_payment_method_does_not_generate_invoice(): void
  {
    Storage::fake('public');

    config([
      'dress.invoice.auto_generate_payment_methods' => ['bank_transfer'],
      'app.url' => 'https://example.test',
    ]);

    $this->bindFakeInvoiceService();

    $data = $this->getOrderData();
    $data['payment']['method'] = 'cash';

    $response = $this->postJson('/api/order', $data);
    $response->assertStatus(200);

    $payload = $response->json();

    $this->assertNull($payload['invoiceDownloadUrl']);
    $this->assertNull($payload['invoiceQrUrl']);
    $this->assertEmpty($this->invoiceServiceFake->generatedOrders);
    $this->assertDatabaseCount('ak_order_invoices', 0);
  }

  public function test_signed_download_falls_back_to_latest_invoice_when_requested_invoice_is_missing(): void
  {
    Storage::fake('public');

    config([
      'dress.invoice.auto_generate_payment_methods' => ['bank_transfer'],
      'app.url' => 'https://example.test',
    ]);

    $this->bindFakeInvoiceService();

    $data = $this->getOrderData();
    $data['payment'] = array_merge($data['payment'], [
      'method' => 'bank_transfer',
      'settlement' => 'Prague',
      'street' => 'Main street',
      'house' => '10A',
      'room' => '5',
      'zip' => '11000',
    ]);

    $response = $this->postJson('/api/order', $data);
    $response->assertStatus(200);

    $payload = $response->json();
    $order = Order::query()->findOrFail($payload['id']);
    $invoice = $order->invoices()->latest('id')->firstOrFail();

    $download = $this->get(sprintf(
      '/api/store/invoices/%d/signed/%d',
      $order->getKey(),
      $invoice->getKey() + 9999
    ));

    $download->assertStatus(200);
    $download->assertHeader('content-type', 'application/pdf');
  }

  public function test_create_with_bonus_applies_discount(): void
  {
    $product = Product::first();
    $this->assertNotNull($product, 'Product seed required for bonus test');

    $orderCurrency = \Store::countryCurrency();
    $bonusPoints = 5.0;
    $bonusFiat = 5.0;

    $price = max(1.0, (float) $product->price);
    $quantity = max(1, (int) ceil(($bonusFiat / $price)) + 1);

    $data = $this->getOrderData();
    $data['products'] = [$product->id => $quantity];
    $data['bonus'] = $bonusPoints;

    $redemption = new BonusRedemption($bonusPoints, $bonusFiat, $orderCurrency, ['wallet_currency' => 'point']);

    $fakeService = new class($redemption) implements BonusService {
      public bool $spendCalled = false;

      public function __construct(private BonusRedemption $redemption)
      {
      }

      public function canSpend(int $userId, float $points): bool
      {
        return true;
      }

      public function spend(int $userId, float $points, string $orderReference, string $orderCurrency, array $context = []): BonusRedemption
      {
        $this->spendCalled = true;
        return $this->redemption;
      }

      public function refund(int $userId, float $points, string $orderReference, string $orderCurrency, array $context = []): void
      {
      }
    };

    $this->app->instance(BonusService::class, $fakeService);
    config(['dress.order.bonus.enabled' => true]);

    $response = $this->postJson('/api/order', $data);
    $response->assertStatus(200);

    $order = Order::latest('id')->first();

    $expectedSubtotal = round($price * $quantity, 2);

    $this->assertTrue($fakeService->spendCalled, 'Bonus service spend should be called');
    $this->assertEquals($expectedSubtotal, (float) $order->subtotal);
    $this->assertEquals($bonusFiat, (float) $order->discount_total);
    $this->assertEquals(round($expectedSubtotal - $bonusFiat, 2), (float) $order->grand_total);
    $this->assertSame($bonusPoints, (float) ($order->info['bonuses']['points'] ?? 0));
    $this->assertFalse((bool) ($order->info['bonuses']['refunded'] ?? true));

    $this->app->instance(BonusService::class, new NullBonusService());
    config(['dress.order.bonus.enabled' => false]);
  }

  public function test_create_sets_totals_correctly(): void
  {
    $data = $this->getOrderData();

    $response = $this->postJson('/api/order', $data);
    $response->assertStatus(200);

    $order = Order::latest('id')->first();

    $expectedSubtotal = 0;
    foreach ($data['products'] as $productId => $qty) {
      $product = Product::find($productId);
      $expectedSubtotal += $product->price * $qty;
    }

    $expectedSubtotal = round($expectedSubtotal, 2);

    $this->assertEquals($expectedSubtotal, (float) $order->subtotal);
    $this->assertEquals(0.0, (float) $order->discount_total);
    $this->assertEquals($expectedSubtotal, (float) $order->grand_total);
    $this->assertEquals($order->grand_total, (float) $order->price);
  }
    
  /**
   * test_create_request_validation_error
   * 
   * Test error handling if empty data during order creation 
   *
   * @return void
   */
  public function test_create_request_validation_error() {
    $response = $this->post('/api/order', []);
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

    $response = $this->post('/api/order', $this->order_data);
    $response->assertStatus(404);
  }

  public function test_validate_order_checks_bonus_balance(): void
  {
    $fakeService = new class implements BonusService {
      public function canSpend(int $userId, float $points): bool
      {
        return false;
      }

      public function spend(int $userId, float $points, string $orderReference, string $orderCurrency, array $context = []): BonusRedemption
      {
        throw new \RuntimeException('Should not spend when canSpend returns false');
      }

      public function refund(int $userId, float $points, string $orderReference, string $orderCurrency, array $context = []): void
      {
      }
    };

    $this->app->instance(BonusService::class, $fakeService);
    config(['dress.order.bonus.enabled' => true]);

    $payload = array_merge($this->getOrderData(), ['bonus' => 5]);
    $response = $this->postJson('/api/order/validate', $payload);

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Недостаточно бонусов на счёте.']);

    $this->app->instance(BonusService::class, new NullBonusService());
    config(['dress.order.bonus.enabled' => false]);
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

    $response = $this->post('/api/order', $this->getOrderData());
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

    $response = $this->post('/api/order', $data);
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

    $response = $this->post('/api/order/copy', ['id' => $order->id]);
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

    $response = $this->post('/api/order/copy', ['id' => $base_order->id]);
    
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
