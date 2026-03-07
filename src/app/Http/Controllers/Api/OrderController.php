<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// MODELS
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Promocode;
use Backpack\Store\app\DTO\ShippingQuoteRequest;
use Backpack\Store\app\Services\Shipping\ShippingCalculator;

// EVENTS
use Backpack\Store\app\Events\ProductAttachedToOrder;
use Backpack\Store\app\Events\PromocodeApplied;

// EXCEPTIONS
use Backpack\Store\app\Exceptions\OrderException;
use Rd\app\Exceptions\DetailedException;
use Backpack\Store\app\Contracts\BonusService;

class OrderController extends \App\Http\Controllers\Controller
{ 
  use \Backpack\Store\app\Traits\Resources;
  use \Rd\app\Traits\RdTrait;

  private $ORDER_MODEL = '';
  private $USER_MODEL = '';

  public $rd_fields = null;
  protected BonusService $bonusService;

  public function __construct() {
    self::resources_init();

    $this->ORDER_MODEL = \Settings::get('dress.order.model', 'Backpack\Store\app\Models\Order');
    $this->USER_MODEL = \Settings::get('dress.store.user_model', 'Backpack\Profile\app\Models\Profile');

    // Rd 
    $this->rd_fields = \Settings::get('dress.order.fields');

    $this->bonusService = app(BonusService::class);
  }
  
  /**
   * index
   *
   * @param  mixed $request
   * @return void
   */
  public function index(Request $request) {

    $user = Auth::guard(\Settings::get('dress.store.auth_guard', 'profile'))->user();

    $orders = $this->ORDER_MODEL::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              ->where('ak_orders.orderable_id', $user->id)
              ->where('ak_orders.orderable_type', $this->USER_MODEL)
              ->when(request('status'), function($query) {
                $query->where('ak_orders.category_id', request('status'));
              })
              ->when(request('price'), function($query) {
                $query->where('ak_orders.price', request('price'));
              })
              ->orderBy('created_at', 'desc');
    
    $per_page = request('per_page', \Settings::get('dress.order.per_page', 12));
    
    $orders = $orders->paginate($per_page);
    $orders = self::$resources['order']['large']::collection($orders);

    return $orders;
  }
  
  /**
   * all
   * 
   * Get Orders collection. Filtering by owner, status, pay_status, delivery_status, price is available.
   * Also you can setup per_page and ordering parametrs.
   *
   * @param  Illuminate\Http\Request $request
   *    [
   *      "orderable_id" => (int) - owner ID
   *      "orderable_type" => (string) - Class/Model/Provider of the owner
   *      "status" => (string) - Common status
   *      "pay_status" => (string) - Payment status
   *      "delivery_status" => (string) - Delivery status
   *      "price" => (float) - total price of the order 
   *      "per_page" => (int) - Pagination, Rows per page 
   *    ]
   * @return string JSON
   */
  public function all(Request $request) {

    $orders = $this->ORDER_MODEL::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              // Owner of the order (user, account, persone etc.)
              ->when(request('orderable_id'), function($query) {
                $query->where('ak_orders.orderable_id', request('orderable_id'));
              })
              ->when(request('orderable_type'), function($query) {
                $query->where('ak_orders.orderable_type', request('orderable_type'));
              })
              //
              ->when(request('status'), function($query) {
                $query->where('ak_orders.category_id', request('status'));
              })
              ->when(request('pay_status'), function($query) {
                $query->where('ak_orders.pay_status', request('pay_status'));
              })
              ->when(request('delivery_status'), function($query) {
                $query->where('ak_orders.delivery_status', request('delivery_status'));
              })
              ->when(request('price'), function($query) {
                $query->where('ak_orders.price', request('price'));
              })
              ->orderBy('created_at', 'desc');
    
    $per_page = request('per_page', \Settings::get('dress.order.per_page', 12));
    
    $orders = $orders->paginate($per_page);
    $orders = self::$resources['order']['large']::collection($orders);

    return $orders;
  }
  
  /**
   * show
   * 
   * Get one order using unique code.
   *
   * @param  mixed $request
   * @param  mixed $code
   * @return void
   */
  public function show(Request $request, $code) {

    try {
      $order = $this->ORDER_MODEL::where('code', $code)->firstOrFail();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return response()->json(new self::$resources['order']['large']($order));
  }
    
  /**
   * validateOrder
   *
   * @param  mixed $request
   * @return void
   */
  public function validateOrder(Request $request) {
    try{
      // Get only allowed fields
      $data = $this->validateData($request);
      $this->ensureCartProductsAvailable($data['products'] ?? []);
      $user = $this->resolveOrderUser($data);
      $this->verifyBonusRequest($data, $user);
      return true;
    }
    catch(DetailedException $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'options' => $e->getOptions()
      ], $e->getCode());
    }
  }


  /**
   * getRequestRules
   *
   * @param  mixed $request
   * @return void
   */
  public function getRequestRules(Request $request) {
    $parsed = $this->parseFieldsConfig($this->rd_fields);

    // 3) Отдаём клиенту
    return response()->json($parsed);
  }


  /**
   * create
   * 
   * Store new product.
   *
   * @param  mixed $request
   * @return void
   */
  public function create(Request $request){
    
    try {
      // Get only allowed fields
      $data = $this->validateData($request);
      $user = $this->resolveOrderUser($data);
      $this->verifyBonusRequest($data, $user);

      [$order, $products] = DB::transaction(function () use ($data, $user) {
        return $this->persistOrder($data, $user);
      });

      ProductAttachedToOrder::dispatch($order);
      if($order->promocode) {
        PromocodeApplied::dispatch($order);
      }

    }catch(DetailedException $e) {
      return response()->json([
        'message' => $e->getMessage(),
        'options' => $e->getOptions()
      ], $e->getCode());
    }

    return response()->json(new self::$resources['order']['large']($order));
  }

  protected function persistOrder(array $data, $user = null): array
  {
    $order = new $this->ORDER_MODEL;
    $includeShipping = $this->shouldIncludeShippingCost($data, $order);

    if(!$user && ($data['provider'] ?? null) === 'auth') {
      $data['provider'] = 'data';
    }

    $order = $this->prepareOrder($order);
    $order = $this->setRequestFields($order, $data);
    $order = $this->setUserData($order, $data, $user);

    [$order, $products] = $this->setProductsToOrder($order, $data);
    $this->syncCampaignInfoFromProducts($order);

    if(!empty($data['promocode'])) {
      $order->promocode = $data['promocode'];
    }

    $this->applyPersonalDiscount($order, $user);

    if ($includeShipping) {
      $shippingQuote = $this->calculateShippingQuote($order, $data);
    } else {
      $this->disableShippingCost($order);
    }

    $totals = $this->calculateBaseTotals($order);

    $order->subtotal = $totals['subtotal'];
    $order->shipping_total = $totals['shipping_total'];
    $order->tax_total = $totals['tax_total'];
    $order->promocode_discount_total = $totals['promocode_discount'];
    $order->personal_discount_total = $totals['personal_discount'];
    $order->campaign_discount_total = $totals['campaign_discount'];
    $order->grand_total = $totals['grand_total'];

    $orderCurrency = $order->currency_code ?? \Store::countryCurrency($order->country_code);
    $info = $order->info ?? [];
    $existingBonuses = $info['bonuses'] ?? [];
    $info['bonusesUsed'] = $info['bonusesUsed'] ?? 0;
    $defaultWalletCurrency = $existingBonuses['wallet_currency'] ?? null;
    $info['bonuses'] = array_merge([
      'points' => 0,
      'fiat_amount' => 0,
      'fiat_currency' => $orderCurrency,
      'order_currency' => $orderCurrency,
      'wallet_currency' => $defaultWalletCurrency,
      'wallet_currency_label' => $defaultWalletCurrency ? store_currency_label($defaultWalletCurrency) : null,
      'refunded' => $existingBonuses['refunded'] ?? false,
      'reference_id' => $existingBonuses['reference_id'] ?? null,
    ], $existingBonuses);
    $order->info = $info;
    $order->bonus_discount_total = 0;
    $order->discount_total = round(
      $order->promocode_discount_total + $order->personal_discount_total + $order->campaign_discount_total,
      2
    );
    $order->price = $order->grand_total;

    $bonusResult = $this->handleBonusSpending(
      $order,
      $data,
      $user,
      $totals['campaign_discount'],
      $totals['promocode_discount'],
      $totals['personal_discount'],
      $totals['grand_total']
    );
    $order->bonus_discount_total = $bonusResult['bonus_discount'];
    $order->discount_total = $bonusResult['total_discount'];
    $order->grand_total = $bonusResult['grand_total'];
    $order->price = $order->grand_total;
    $order->info = $bonusResult['info'];

    $order->save();

    $this->attachProductsToOrder($order, $products, $data);

    return [$order, $products];
  }

  protected function calculateShippingQuote(Order $order, array $data): ?\Backpack\Store\app\DTO\ShippingQuoteResult
  {
    $methodKey = data_get($data, 'delivery.method');
    $destination = data_get($data, 'destinationCountry')
      ?? data_get($data, 'shipping_country_code')
      ?? \Store::country();

    if(!$methodKey || !$destination) {
      return null;
    }

    /** @var ShippingCalculator $calculator */
    $calculator = app(ShippingCalculator::class);
    $quoteRequest = new ShippingQuoteRequest([
      'methodKey' => $methodKey,
      'destinationCountry' => $destination,
      'weightG' => $this->resolveShipmentWeight($order, $data),
      'meta' => $this->buildShippingMeta($order, $data),
    ]);

    $quote = $calculator->calculate($quoteRequest);

    $order->shipping_total = round($quote->amount, 2);

    $info = $order->info ?? [];
    $info['shippingQuote'] = [
      'provider' => $quote->provider,
      'methodKey' => $quote->methodKey,
      'currency' => $quote->currency,
      'amount' => $quote->amount,
      'breakdown' => $quote->breakdown,
    ];
    $order->info = $info;

    return $quote;
  }

  protected function shouldIncludeShippingCost(array $data, ?Order $order = null): bool
  {
    $country = $this->resolveShippingCountry($data, $order);

    if ($country) {
      return (bool) \Settings::get('shipping.add_to_order_enabled', false, ['country' => $country]);
    }

    return (bool) \Settings::get('shipping.add_to_order_enabled', false);
  }

  protected function resolveShippingCountry(array $data, ?Order $order = null): ?string
  {
    $country = data_get($data, 'destinationCountry')
      ?? data_get($data, 'shipping_country_code')
      ?? ($order ? $order->country_code : null)
      ?? \Store::country();

    $country = $country ? strtoupper((string) $country) : null;

    return $country ?: null;
  }

  protected function disableShippingCost(Order $order): void
  {
    $info = $order->info ?? [];
    unset($info['shippingQuote']);
    $order->info = $info;
    $order->shipping_total = 0.0;
  }

  protected function buildShippingMeta(Order $order, array $data): array
  {
    $subtotal = round($order->getProductsPrice(), 2);

    $meta = [
      'order_code' => $order->code,
      'subtotal' => $subtotal,
      'campaign_discount' => $this->calculateCampaignDiscount($order),
      'promocode_discount' => $this->calculatePromocodeDiscount($order),
      'bonus_discount' => $this->resolveBonusDiscountPreview($data, $order),
      'personal_discount' => round((float)($order->personal_discount_total ?? 0), 2),
      'currency' => $order->currency_code ?? \Store::countryCurrency($order->country_code),
    ];

    if(isset($data['bonus'])) {
      $meta['bonus_points'] = (float)$data['bonus'];
    }

    return $meta;
  }

  protected function resolveBonusDiscountPreview(array $data, Order $order): float
  {
    if(isset($data['bonusInFiat'])) {
      $requested = max(0.0, round((float)$data['bonusInFiat'], 2));

      $availableBase = round($order->getProductsPrice(), 2)
        - $this->calculatePromocodeDiscount($order)
        - round((float)($order->personal_discount_total ?? 0), 2);

      $availableBase = max(0.0, $availableBase);

      return min($requested, round($availableBase, 2));
    }

    return 0.0;
  }

  protected function calculatePromocodeDiscount(Order $order): float
  {
    $subtotal = round($order->getProductsPrice(), 2);
    $priceWithPromocode = round($order->getTotalPrice(), 2);
    $discount = round($subtotal - $priceWithPromocode, 2);

    if($discount < 0) {
      $discount = 0.0;
    }

    if($discount > $subtotal) {
      $discount = $subtotal;
    }

    return $discount;
  }

  protected function calculateCampaignDiscount(Order $order): float
  {
    $products = (array) data_get($order->info, 'products', []);
    if (empty($products)) {
      return 0.0;
    }

    $total = 0.0;

    foreach ($products as $item) {
      if (!is_array($item)) {
        continue;
      }

      $amount = max(1, (float) ($item['amount'] ?? 1));

      if (isset($item['campaignDiscount'])) {
        $total += max(0, (float) $item['campaignDiscount']) * $amount;
        continue;
      }

      $basePrice = $item['basePrice'] ?? null;
      $price = $item['price'] ?? null;

      if ($basePrice === null || $price === null) {
        continue;
      }

      $lineDiscount = max(0, (float) $basePrice - (float) $price);
      $total += $lineDiscount * $amount;
    }

    return round(max(0, $total), 2);
  }

  protected function applyPersonalDiscount(Order $order, $user = null): float
  {
    $info = $order->info ?? [];
    $allowed = \Settings::get('profile.users.allow_personal_discount');

    if(!$allowed || !$user) {
      $info['personalDiscount'] = [
        'percent' => 0.0,
        'amount' => 0.0,
        'applied' => false,
        'currency' => $order->currency_code ?? \Store::countryCurrency($order->country_code),
      ];
      $order->info = $info;
      $order->personal_discount_total = 0.0;
      return 0.0;
    }

    $percent = $this->resolvePersonalDiscountPercent($user);
    $percent = max(0.0, min(100.0, (float)$percent));

    $subtotal = round($order->getProductsPrice(), 2);

    if($percent <= 0 || $subtotal <= 0) {
      $info['personalDiscount'] = [
        'percent' => round($percent, 2),
        'amount' => 0.0,
        'applied' => false,
        'currency' => $order->currency_code ?? \Store::countryCurrency($order->country_code),
      ];
      $order->info = $info;
      $order->personal_discount_total = 0.0;
      return 0.0;
    }

    $amount = round($subtotal * ($percent / 100), 2);
    $amount = min($amount, $subtotal);

    $info['personalDiscount'] = [
      'percent' => round($percent, 2),
      'amount' => $amount,
      'applied' => $amount > 0,
      'currency' => $order->currency_code ?? \Store::countryCurrency($order->country_code),
    ];

    $order->info = $info;
    $order->personal_discount_total = $amount;

    return $amount;
  }

  protected function resolvePersonalDiscountPercent($user): float
  {
    if(!$user) {
      return 0.0;
    }

    $percent = null;

    try {
      $percent = data_get($user, 'personal_discount_percent');
    } catch (\Throwable $e) {
      $percent = null;
    }

    if($percent === null) {
      try {
        $percent = data_get($user, 'discount_percent');
      } catch (\Throwable $e) {
        $percent = null;
      }
    }

    if($percent === null && method_exists($user, 'relationLoaded') && method_exists($user, 'loadMissing') && method_exists($user, 'profile')) {
      $user->loadMissing('profile');
      $percent = data_get($user->profile, 'discount_percent');
    }

    return $percent !== null ? (float)$percent : 0.0;
  }

  protected function resolveShipmentWeight(Order $order, array $data): int
  {
    return 1000;
  }

  protected function attachProductsToOrder($order, $products, array $data): void
  {
    foreach($products as $product) {
      $order->products()->attach($product, [
        'amount' => $data['products'][$product->id], 
        'value' => $product->price,
        'currency_code' => $order->currency_code,
        'country_code' => $order->country_code,
        'supplier_id' => $product->supplier->id
      ]);
    }
  }

  protected function calculateBaseTotals(Order $order): array
  {
    $subtotal = round($order->getProductsPrice(), 2);
    $shipping = round((float)($order->shipping_total ?? 0), 2);
    $tax = round((float)($order->tax_total ?? 0), 2);

    $campaignDiscount = $this->calculateCampaignDiscount($order);
    $campaignDiscount = min($campaignDiscount, $subtotal);
    $promocodeDiscount = $this->calculatePromocodeDiscount($order);
    $personalDiscount = round((float)($order->personal_discount_total ?? 0), 2);
    $personalDiscount = min($personalDiscount, $subtotal);

    $grandTotal = max(
      0,
      round($subtotal - $promocodeDiscount - $personalDiscount + $shipping + $tax, 2)
    );

    return [
      'subtotal' => $subtotal,
      'shipping_total' => $shipping,
      'tax_total' => $tax,
      'campaign_discount' => $campaignDiscount,
      'promocode_discount' => $promocodeDiscount,
      'personal_discount' => $personalDiscount,
      'grand_total' => $grandTotal,
    ];
  }

  protected function handleBonusSpending(
    Order $order,
    array $data,
    $user,
    float $campaignDiscount,
    float $promocodeDiscount,
    float $personalDiscount,
    float $baseGrandTotal
  ): array
  {
    $bonusPoints = isset($data['bonus']) ? (float)$data['bonus'] : 0.0;
    $info = $order->info ?? [];

    if(!$this->bonusFeatureEnabled() || $bonusPoints <= 0 || !$user) {
      return [
        'bonus_discount' => 0.0,
        'total_discount' => round($campaignDiscount + $promocodeDiscount + $personalDiscount, 2),
        'grand_total' => $baseGrandTotal,
        'info' => $info,
      ];
    }

    $orderCurrency = $order->currency_code ?? \Store::countryCurrency($order->country_code);

    try {
      $redemption = $this->bonusService->spend(
        $user->id,
        $bonusPoints,
        (string)$order->code,
        $orderCurrency,
        [
          'reference_type' => 'order',
          'reference_id' => (string)$order->code,
          'order_code' => $order->code,
        ]
      );
    } catch (\Throwable $exception) {
      throw new DetailedException('Не удалось списать бонусы. Попробуйте ещё раз.', 422, $exception);
    }

    $availableToDiscount = max(0, $order->subtotal - $promocodeDiscount - $personalDiscount);
    $bonusFiat = min(round($redemption->fiatAmount, 2), round($availableToDiscount, 2));

    $totalDiscount = round($campaignDiscount + $promocodeDiscount + $personalDiscount + $bonusFiat, 2);
    $grandTotal = max(0, round(
      $order->subtotal
      - $promocodeDiscount
      - $personalDiscount
      - $bonusFiat
      + (float)$order->shipping_total
      + (float)$order->tax_total,
      2
    ));

    $walletCurrency = $redemption->meta['wallet_currency'] ?? null;

    $info['bonusesUsed'] = $bonusFiat;
    $info['bonuses'] = array_merge($info['bonuses'] ?? [], [
      'points' => round($redemption->points, 2),
      'fiat_amount' => $bonusFiat,
      'fiat_currency' => $redemption->fiatCurrency,
      'order_currency' => $orderCurrency,
      'wallet_currency' => $walletCurrency,
      'wallet_currency_label' => $walletCurrency ? store_currency_label($walletCurrency) : null,
      'requested_points' => isset($data['bonus']) ? (float)$data['bonus'] : null,
      'requested_fiat' => isset($data['bonusInFiat']) ? (float)$data['bonusInFiat'] : null,
      'meta' => $redemption->meta,
      'refunded' => false,
      'reference_id' => (string)$order->code,
    ]);

    return [
      'bonus_discount' => $bonusFiat,
      'total_discount' => $totalDiscount,
      'grand_total' => $grandTotal,
      'info' => $info,
    ];
  }

  protected function syncCampaignInfoFromProducts(Order $order): void
  {
    $products = (array) data_get($order->info, 'products', []);
    $campaigns = [];

    foreach ($products as $product) {
      if (!is_array($product) || empty($product['campaign']) || !is_array($product['campaign'])) {
        continue;
      }

      $campaign = $product['campaign'];
      $id = (int) ($campaign['id'] ?? 0);
      if ($id <= 0) {
        continue;
      }

      if (!isset($campaigns[$id])) {
        $campaigns[$id] = [
          'id' => $id,
          'slug' => $campaign['slug'] ?? null,
          'name' => $campaign['name'] ?? null,
          'discount_percent' => (float) ($campaign['discount_percent'] ?? 0),
        ];
      }
    }

    $info = $order->info ?? [];
    $info['campaigns'] = array_values($campaigns);
    $order->info = $info;
  }

  /**
   * setUserData
   * 
   * Set user data to order info field and attach user Model if possible.
   * 
   * @param  Backpack\Store\app\Models\Order $order - new Order model
   * @param  array $data - Order request data
   * @return Backpack\Store\app\Models\Order $order
   */
  protected function setUserData($order, array $data, $user_model = null){
    $user = $user_model ?? $this->resolveOrderUser($data);

    if(!$user) {
      return $order;
    }

    // User Model have to implement toOrderArray() method that gives:
    //    array {first_name: string, last_name: string, phone: string, email: string}
    $user_data = $user->toOrderArray();

    $resolved_user_data = $this->resolveUserData($user_data, $data);

    // add user data to info field (json)
    $info = $order->info;
    $info['user'] = $resolved_user_data;
    $order->info = $info;

    $order->orderable_id = $user->id ?? null;
    $order->orderable_type = \Settings::get('dress.store.user_model', 'Backpack\Profile\app\Models\Profile');

    return $order;
  }

  protected function resolveUserData($authData, $requestData) {
    if(!empty($requestData['user']['phone'])) {
      $authData['phone'] = $requestData['user']['phone'];
    }

    if(!empty($requestData['user']['email'])) {
      $authData['email'] = $requestData['user']['email'];
    }

    if(!empty($requestData['user']['first_name'])) {
      $authData['first_name'] = $requestData['user']['first_name'];
    }

    if(!empty($requestData['user']['last_name'])) {
      $authData['last_name'] = $requestData['user']['last_name'];
    }

    return $authData;
  }

  protected function resolveOrderUser(array $data)
  {
    if(($data['provider'] ?? null) !== 'auth') {
      return null;
    }

    $guard = \Settings::get('dress.store.auth_guard', 'profile');

    if(!Auth::guard($guard)->check()){
      return null;
    }

    return Auth::guard($guard)->user();
  }

  protected function verifyBonusRequest(array $data, $user = null): void
  {
    $bonusPoints = isset($data['bonus']) ? (float)$data['bonus'] : 0.0;

    if($bonusPoints <= 0) {
      return;
    }

    if(!$this->bonusFeatureEnabled()) {
      throw new DetailedException('Использование бонусов недоступно для этого заказа.', 422);
    }

    if(!$user) {
      throw new DetailedException('Использование бонусов доступно только авторизованным пользователям.', 401);
    }

    if(!$this->bonusService->canSpend($user->id, $bonusPoints)) {
      throw new DetailedException('Недостаточно бонусов на счёте.', 422);
    }
  }

  protected function bonusFeatureEnabled(): bool
  {
    $enabledFlag = \Settings::get('profile.pay_for_order.enabled');

    // if ($enabledFlag === null) {
    //   $enabledFlag = \Settings::get('dress.order.enable_bonus', false);
    // }

    return (bool)$enabledFlag;
  }


  /**
   * prepareOrder
   *
   * @param Backpack\Store\app\Models\Order $order
   * @return Backpack\Store\app\Models\Order $order
   */
  protected function prepareOrder($order) {
    // Generate order code
    $order->code = random_int(100000, 999999);

    // Generate order code
    $order->status = \Settings::get('dress.order.status.default', 'new');
    
    // Generate order code
    $order->pay_status = \Settings::get('dress.order.pay_status.default', 'waiting');
    
    // Generate order code
    $order->delivery_status = \Settings::get('dress.order.delivery_status.default', 'waiting');

    return $order;
  }
  
  /**
   * setProductsToOrder
   * 
   * @param  Backpack\Store\app\Models\Order $order - new Order model
   * @param  array $data - Order request data
   * @return array {$order: Backpack\Store\app\Models\Order, $products: Collection}
   */
  protected function setProductsToOrder($order, array $data){
    // Get products collection
    $products = $this->ensureCartProductsAvailable($data['products'], true);

    if(!$products || !$products->count()) {
      throw new OrderException("There are no products found in cart or products does not exist in the database or products don't available.", 404);
    }

    // Set products to info
    foreach($products as $key => $product) {
      $product->amount = $data['products'][$product->id];
      $product->currency = \Store::countryCurrency();
      $info = $order->info;
      $info['products'][$key] = new self::$resources['product']['cart']($product);
      $order->info = $info;
    }

    return [$order, $products];
  }

  protected function ensureCartProductsAvailable(array $productAmounts, bool $returnProducts = false)
  {
    $requestedIds = array_values(array_map('intval', array_keys($productAmounts ?: [])));

    if(empty($requestedIds)) {
      return $returnProducts ? collect() : null;
    }

    $query = Product::whereIn('id', $requestedIds)->available();

    if($returnProducts) {
      $products = $query->get();
      $foundIds = $products->pluck('id')->map(function ($id) {
        return (int)$id;
      })->all();
    }else {
      $foundIds = $query->pluck('id')->map(function ($id) {
        return (int)$id;
      })->all();
    }

    $missing = array_values(array_diff($requestedIds, $foundIds));

    if(!empty($missing)) {
      $this->throwCartProductsUnavailable($missing);
    }

    return $returnProducts ? $products : null;
  }

  protected function throwCartProductsUnavailable(array $missingIds)
  {
    throw new DetailedException($this->cartProductsUnavailableMessage(), 422, null, [
      'missingProducts' => $missingIds
    ]);
  }

  protected function cartProductsUnavailableMessage(): string
  {
    return 'Product unavailable in your region';
  }

  /**
   * usePromocode
   * 
   * Apply promocode to order after validation. 
   * The promocode affects the order price.
   *
   * @param  Backpack\Store\app\Models\Order $order - new order Model
   * @param  array $data - Data from the order request
   * @return Backpack\Store\app\Models\Order $order
   */
  // protected function usePromocode($order, $data) {
  //   // Checking if promocode data isset in request
  //   if(!isset($data['promocode']) || empty($data['promocode']) || !$order)
  //     return $order;
    
  //   // Checking if promocode really excists in DB and getting it. 
  //   $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($data['promocode'])).'%'])->first();
    
  //   // Check if promocode valid by used times, date and is_active property
  //   if(!$promocode || !$promocode->isValid)
  //     return $order;
    
  //   // Setting promocode info to order's info
  //   $info = $order->info;
  //   $info['promocode'] = $promocode;
  //   $order->info = $info;

  //   // Making correction to order price
  //   // if promocode is expressed in currency 
  //   if($promocode->type === 'value')
  //     $order->price = $order->price - $promocode->value;

  //   // if promocode is expressed in percent
  //   if($promocode->type === 'percent')
  //     $order->price = $order->price - ($order->price * $promocode->value / 100);
    
  //   // return changed order
  //   return $order;
  // }
  
  /**
   * copy
   * 
   * Clone existing order to a new 
   *
   * @param  mixed $request
   *    [
   *      "id" => (int) Base order id
   *    ]
   * @return Backpack\Store\app\Models\Order $order
   */
  public function copy(Request $request) {
    // Getting base order id from request
    if(!$request->id)
      throw new \Exception('The ID of the base record was not pass.', 403);
  
    // Getting base order
    $base = Order::findOrFail($request->id);
    
    // Clone base order to variable
    $order = $base->replicate();

    try {
      // Reset total price, statuses, delete promocodes and bonuses
      $order->resetCopy();

      // Try to save order
      $order->save();
    }catch(\Exception $e) {
      throw new \Exception('An error has occurred. Failed to create copy: ' . $e->getMessage(), $e->getCode());
    }

    return $order;
  }

  /**
   * Рекурсивно «разворачивает» вложенную конфигурацию в единый массив,
   * где для каждого поля будут чётко разобранные ключи:
   * - required (bool)
   * - nullable (bool)
   * - in (array)
   * - min (int)
   * - max (int)
   * - type (string|null) — например "string", "uuid", "array", "email" и т.д.
   * - requiredIf: [ 'field' => 'другой.путь', 'values' => [ ... ] ] (если есть required_if)
   * - storeIn: (string|null)
   * - hidden: (bool)
   * - children: (если внутри есть вложенные поля) — аналогичный формат для каждого дочернего поля
   *
   * @param  array  $fieldsConfig
   * @return array
   */
  private function parseFieldsConfig(array $fieldsConfig): array
  {
      $result = [];

      foreach ($fieldsConfig as $fieldName => $config) {
          $meta = [
              'required'   => false,
              'nullable'   => false,
              'in'         => null,
              'min'        => null,
              'max'        => null,
              'type'       => null,
              'requiredIf' => null,
              'storeIn'    => null,
              'hidden'     => false,
          ];

          // 1) Если есть ключ 'store_in'
          if (isset($config['store_in'])) {
              $meta['storeIn'] = $config['store_in'];
          }

          // 2) Если помечено hidden
          if (! empty($config['hidden'])) {
              $meta['hidden'] = true;
          }

          // 3) Если есть строка 'rules', то её надо распарсить
          if (isset($config['rules']) && is_string($config['rules'])) {
              // Разбиваем по '|'
              $rulesList = explode('|', $config['rules']);

              foreach ($rulesList as $rule) {
                  // Если внутри есть двоеточие, значит, это правило с параметрами
                  if (strpos($rule, ':') !== false) {
                      [$ruleName, $ruleParams] = explode(':', $rule, 2);

                      switch ($ruleName) {
                          case 'required':
                              // "required" как правило без аргументов (но в Laravel бывает в составе "required_if" и пр.)
                              $meta['required'] = true;
                              break;

                          case 'nullable':
                              $meta['nullable'] = true;
                              break;

                          case 'in':
                              // in:auth,data,outer → ['auth','data','outer']
                              $meta['in'] = explode(',', $ruleParams);
                              break;

                          case 'max':
                              $meta['max'] = (int) $ruleParams;
                              break;

                          case 'min':
                              $meta['min'] = (int) $ruleParams;
                              break;

                          case 'required_if':
                              // Формат Laravel: required_if:другойПоле,значение1,значение2,...
                              $parts = explode(',', $ruleParams);
                              $otherField = array_shift($parts);
                              $meta['requiredIf'] = [
                                  'field'  => $otherField,
                                  'values' => $parts, // может быть сразу массив из нескольких значений
                              ];
                              break;

                          case 'array':
                              // array:settlement,settlementRef,...
                              $meta['type'] = 'array';
                              // Прямо в JSON отдадим, какие ключи внутри этого массива могут придти
                              $meta['keys'] = explode(',', $ruleParams);
                              break;

                          default:
                              // Тут могут быть и другие «типовые» правила, например:
                              // string, uuid, email, numeric и т.д. без «:»
                              // Но если это не самый первый фрагмент, а правило с параметром, 
                              // может быть что это «regex» или нестандартные.
                              // Для простоты зафиксируем некоторые распространённые случаи.
                              if (in_array($ruleName, ['string', 'uuid', 'email', 'numeric'])) {
                                  $meta['type'] = $ruleName;
                              }
                              // Если что-то нераспознано, можно игнорировать или сохранять «как есть».
                              break;
                      }
                  } else {
                      // Простые правила без «:», например: required, string, uuid, email, numeric
                      switch ($rule) {
                          case 'required':
                              $meta['required'] = true;
                              break;
                          case 'nullable':
                              $meta['nullable'] = true;
                              break;
                          case 'string':
                          case 'uuid':
                          case 'email':
                          case 'numeric':
                              // если типа «string|nullable|min:2|max:100», то тип «string» переопределяется здесь
                              $meta['type'] = $rule;
                              break;
                          // Можно добавить другие простые правила, если нужно
                          default:
                              break;
                      }
                  }
              }
          }

          // 4) Проверяем, есть ли в $config вложенные «дочерние» поля (children).
          //    Мы считаем «дочерними» любые ключи, кроме: «rules», «store_in», «hidden».
          $childrenKeys = array_diff(array_keys($config), ['rules', 'store_in', 'hidden']);
          if (! empty($childrenKeys)) {
              $childConfig = [];
              foreach ($childrenKeys as $ck) {
                  $childConfig[$ck] = $config[$ck];
              }
              // Рекурсивно парсим всё, что в «детях»
              $meta['children'] = $this->parseFieldsConfig($childConfig);
          }

          // Готово: кладём результат в итоговый массив
          $result[$fieldName] = $meta;
      }

      return $result;
  }
}
