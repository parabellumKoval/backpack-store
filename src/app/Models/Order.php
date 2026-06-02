<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\OrderFactory;

// TRAITS
use App\Models\Traits\OrderModel as OrderModelTrait;

// Arr
use Illuminate\Support\Arr;

// EVENTS
use Backpack\Store\app\Events\PromocodeApplied;
use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Models\Promocode;
use Backpack\Store\app\Models\OrderInvoice;

//
use Backpack\Helpers\Traits\HasDisplayLabel;
use Backpack\Helpers\Traits\FormatsUniqAttribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasDisplayLabel;
    use CrudTrait;
    use HasFactory;
    use FormatsUniqAttribute;

    use OrderModelTrait;
    use \Backpack\Store\app\Traits\Resources;
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_orders';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = ['price', 'productsRelated', 'extras', 'delivery_status', 'pay_status', 'status','country_code', 'storefront_code', 'currency_code', 'fx_rate',
        'subtotal','discount_total','promocode_discount_total','bonus_discount_total','personal_discount_total','campaign_discount_total','shipping_total','tax_total','grand_total',];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'info' => 'array',
      'productsRelated' => 'array',
      'extras' => 'array',
      'user' => 'array'
    ];

    public $products_to_synk = [];

    protected $dispatchesEvents = [
      'created' => OrderCreated::class
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    protected static function booted(): void
    {
        static::creating(function (self $order) {
            // Безопасно подставляем из текущего Store-контекста
            if (!$order->country_code) {
                $order->country_code = \Store::country();
            }
            if (!$order->storefront_code) {
                $order->storefront_code = \Store::storefront();
            }
            if (!$order->currency_code) {
                $order->currency_code = \Store::countryCurrency($order->country_code);
            }
            if (!$order->fx_rate) {
                $order->fx_rate = app(\Backpack\Store\app\Contracts\ExchangeRateProvider::class)
                    ->getExchangeRate(\Settings::get('dress.store.base_currency'), $order->currency_code);
            }

            $info = $order->info ?? [];
            $info['storefront'] = $info['storefront'] ?? $order->storefront_code ?? \Store::storefront();
            $order->info = $info;
        });
    }
    
    /**
     * __construct
     *
     * @param  mixed $attributes
     * @return void
     */
    public function __construct(array $attributes = array()) {
      parent::__construct($attributes);
      self::resources_init();
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return OrderFactory::new();
    }

    protected function displayLabelConfig(): array
    {
        // Считаем всё заранее и просто возвращаем массив
        $prefix = 'Заказ';
        $code   = $this->code ?? $this->getKey();
        $user = $this->orderable && $this->orderable->name? '👨‍💻 ' . $this->orderable->name: null; 
        $time   = '🕒 ' . $this->created_at->format('Y-m-d H:i');
        $sum    = $this->price !== null
            ? number_format((float)$this->price, 2, '.', ' ').' '.(string)($this->currency_code ?? '')
            : null;

        return [
            'prefix' => $prefix,
            'parts'   => array_filter([$code, $user, $time, $sum]),
            'join'    => ' / ',
            'country' => $this->country_code ?? '',
            'storefront' => $this->storefront_code ?? data_get($this->info, 'storefront'),
            'html_template' => 'crud::columns.order_display_label'
        ];
    }

    /**
     * resetCopy
     * 
     * Reset some fields during coping order
     *
     * @return void
     */
    public function resetCopy() {
      // Get current info data
      $info = $this->info;

      // Remove used bonuses data
      $info['bonusesUsed'] = 0;
      if(isset($info['bonuses'])) {
        $info['bonuses']['points'] = 0;
        $info['bonuses']['fiat_amount'] = 0;
        $info['bonuses']['refunded'] = false;
        $info['bonuses']['order_currency'] = $this->currency_code ?? \Store::countryCurrency($this->country_code);
      } else {
        $info['bonuses'] = [
          'points' => 0,
          'fiat_amount' => 0,
          'fiat_currency' => $this->currency_code ?? \Store::countryCurrency($this->country_code),
          'order_currency' => $this->currency_code ?? \Store::countryCurrency($this->country_code),
          'refunded' => false,
        ];
      }

      // Reset promocode
      $info['promocode'] = null;

      // Generate new order code 
      $this->code = random_int(100000, 999999);

      // Reset totals (without promocodes and bonuses)
      $base = $this->getProductsPrice();
      $this->subtotal = $base;
      $this->promocode_discount_total = 0;
      $this->bonus_discount_total = 0;
      $this->personal_discount_total = 0;
      $this->campaign_discount_total = 0;
      $this->discount_total = 0;
      $this->shipping_total = 0;
      $this->tax_total = 0;
      $this->grand_total = $base;
      $this->price = $base;

      // Reset statuses
      $this->status = \Settings::get("dress.order.status.default");
      $this->pay_status = \Settings::get("dress.order.pay_status.default");
      $this->delivery_status = \Settings::get("dress.order.delivery_status.default");

      // Write clear info
      $this->info = $info;
    }
    
    /**
     * getProductsPrice
     * 
     * Calculate order total price from info array
     * without tax, promocodes, bonuses etc. Using only product price and product amount
     *
     * @return float $price
     */
    public function getProductsPrice() {
      $products = $this->info['products'];
      
      // Calculate price
      $price = array_reduce($products, function($carry, $item) {
        return $carry + $item['price'] * $item['amount'];
      }, 0);

      // Для заказов всегда две цифры (сотые)
      return round($price, 2);
    }
    
    /**
     * getTotalPrice
     * 
     * Total price using products price, promocodes etc.
     *
     * @return void
     */
    public function getTotalPrice() {
      // Get sum of products with amount
      $price = $this->getProductsPrice();
      // If no promocode return regular price
      if(!isset($this->info['promocode']) || empty($this->info['promocode'])) {
        return $price;
      }

      // Try find promocode data in info JSON
      $promocode = $this->info['promocode'];

      // Making correction to order price
      // if promocode is expressed in currency 
      if($promocode['type'] === 'value') {
        $price = $price - $promocode['value'];
      }

      // if promocode is expressed in percent
      if($promocode['type'] === 'percent')
        $price = $price - ($price * $promocode['value'] / 100);

      // Для заказов всегда две цифры (сотые)
      return round($price, 2);
    }

    public function invoices()
    {
      return $this->hasMany(OrderInvoice::class);
    }
    
    /**
     * usePromocode
     * 
     * Making 2 things:
     * -- Set promocode info to info JSON
     * -- Set total order price
     *
     * @param  mixed $promocode
     * @return void
     */
    public function usePromocode(string $promocode): void {
      
      // Set promocode to info JSON
      $this->promocode = $promocode;
      
      // Refresh price (using promocode sale)
      $this->price = $this->getTotalPrice();
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function products()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Product', 'ak_order_product')
                  ->withPivot(['amount', 'value', 'currency_code', 'country_code', 'supplier_id']);
    }

    // Owner/User Model/ Profile Model etc.
    public function orderable(): MorphTo
    {
      return $this->morphTo();
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */
    public function scopeForCountry(Builder $q, ?string $code): Builder
    {
        return $code ? $q->where('country_code', $code) : $q;
    }


    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getUniqStringAttribute(): string
    {
        $sum = $this->price !== null
            ? sprintf('%s %s', number_format((float) $this->price, 2, '.', ' '), $this->currency_code ?? '')
            : null;

        return $this->formatUniqString([
            '#'.$this->id,
            'code: '.$this->code,
            $sum ? 'total: '.$sum : null,
            sprintf('status: %s', $this->status ?? '-'),
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $sum = $this->price !== null
            ? sprintf('%s %s', number_format((float) $this->price, 2, '.', ' '), $this->currency_code ?? '')
            : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            $this->code ? 'code: '.$this->code : null,
        ]);

        return $this->formatUniqHtml($headline, [
            $sum ? 'total: '.$sum : null,
            sprintf('status: %s', $this->status ?? '-'),
            $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ]);
    }
    
    /**
     * getUserAttribute
     * 
     * Return user data array from order info JSON
     *
     * @return array|null
     */
    public function getUserAttribute() {
      if(isset($this->info['user']) && $this->info['user'] && count($this->info['user']))
        return $this->info['user'];
      else
        return null;
    }
    
    /**
     * getDeliveryAttribute
     *
     * Return delivery data array from order info JSON
     * 
     * @return array|null
     */
    public function getDeliveryAttribute() {
      if(isset($this->info['delivery']) && $this->info['delivery'] && count($this->info['delivery']))
        return $this->info['delivery'];
      else
        return null;
    }
    
    /**
     * getPaymentAttribute
     *
     * Return payment data array from order info JSON
     *
     * @return array|null
     */
    public function getPaymentAttribute() {
      if(isset($this->info['payment']) && $this->info['payment'] && count($this->info['payment']))
        return $this->info['payment'];
      else
        return null;
    }
    
    /**
     * getProductsAnywayAttribute
     * 
     * Return products from info JSON or from relations otherwise
     *
     * @return array
     */
    public function getProductsAnywayAttribute() {
      // Try get products from static info field
      if(isset($this->info['products']) && $this->info['products'] && count($this->info['products'])) {
        return $this->info['products'];
      }
      // try get products from relations
      elseif($this->products) {
        // Get products collection resource 
        $products_collection = self::$resources['product']['cart']::collection($this->products);
        
        // Convert to array and return
        return json_decode($products_collection->toJson(), true);
      }
      // else return empty array
      else {
        return [];
      }
    }
    
    
    /**
     * getPromocodeAttribute
     *
     * Get promocode info from order info
     * 
     * @return array|null
     */
    public function getPromocodeAttribute() {
      if(!isset($this->info['promocode']) || empty($this->info['promocode']))
        return null;

      return $this->info['promocode'];
    }
        
    public function getCurrencyAttribute() {
      return $this->currency_code ?? null;
    }
    /**
     * getPromocodeSaleStringAttribute
     * 
     * Necessary for email-letters and dashboard
     * Return sale value in currency or in percents
     * Fx: -50$ or -5%
     * 
     * @return string
     */
    public function getPromocodeSaleStringAttribute() {
      if(!$this->promocode)
        return '';
      
      $currency_symbol = \Settings::get("dress.store.currency.symbol", '$'); 

      switch($this->promocode['type']) {
        // If regular return in currency
        case 'value':
          return "-{$currency_symbol}{$this->promocode['value']}";
        // If percents
        case 'percent':
          return "-{$this->promocode['value']}%";
        default:
          return $this->promocode['value'];
      }
    }

    public function getInvoiceDownloadUrlAttribute(): ?string
    {
        $invoice = $this->resolveInvoiceForLinks();

        if (!$invoice) {
            return null;
        }

        return app(\Backpack\Store\app\Services\Invoice\InvoiceService::class)->signedUrl($invoice);
    }

    public function getInvoiceQrUrlAttribute(): ?string
    {
        $invoice = $this->resolveInvoiceForLinks();

        if (!$invoice || !$invoice->qr_path) {
            return null;
        }

        $disk = (string) ($this->invoiceConfig('qr.cache_disk') ?: $this->invoiceConfig('storage.disk', 'public') ?: 'public');

        $url = Storage::disk($disk)->url($invoice->qr_path);

        if (!$url) {
            return null;
        }

        $normalizedPublicPath = rtrim(str_replace('\\', '/', public_path()), '/');
        $normalizedUrl = str_replace('\\', '/', $url);

        if (Str::startsWith($normalizedUrl, $normalizedPublicPath)) {
            $relative = ltrim(Str::after($normalizedUrl, $normalizedPublicPath), '/');
            return URL::to($relative ? '/' . $relative : '/');
        }

        if (!Str::startsWith($normalizedUrl, ['http://', 'https://'])) {
            return URL::to(Str::start($normalizedUrl, '/'));
        }

        return $normalizedUrl;
    }

    public function requiresInvoice(): bool
    {
        $method = $this->invoicePaymentMethod();

        if (!$method) {
            return false;
        }

        return in_array($method, $this->invoiceTriggerMethods(), true);
    }

    protected function resolveInvoiceForLinks(): ?OrderInvoice
    {
        if (!$this->requiresInvoice()) {
            return null;
        }

        return $this->invoices()
            ->whereNotNull('path')
            ->latest('generated_at')
            ->latest('id')
            ->first();
    }

    protected function invoicePaymentMethod(): ?string
    {
        $method = $this->extractMethodKey(data_get($this->info, 'payment'));

        return $method !== null ? strtolower($method) : null;
    }

    protected function invoiceTriggerMethods(): array
    {
        $configured = $this->invoiceConfig('auto_generate_payment_methods', []);

        if (is_string($configured)) {
            $configured = array_filter(array_map('trim', explode(',', $configured)));
        }

        return array_map(static function ($value) {
            return strtolower((string) $value);
        }, (array) $configured);
    }

    protected function invoiceConfig(string $key, $default = null)
    {
        $value = \Settings::get("dress.invoice.$key");

        if ($value === null) {
            $value = config("dress.invoice.$key", $default);
        }

        return $value ?? $default;
    }

    protected function extractMethodKey(mixed $payload): ?string
    {
        if (is_string($payload)) {
            $payload = trim($payload);
            return $payload !== '' ? $payload : null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $keys = ['method', 'paymentMethod', 'payment_method', 'deliveryMethod', 'delivery_method', 'methodKey', 'method_key', 'code', 'key', 'name', 'label'];

        $extract = static function (array $source) use (&$extract, $keys): ?string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $source)) {
                    continue;
                }

                $value = $source[$key];

                if (is_scalar($value) || is_bool($value)) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        return $value;
                    }
                }

                if (is_array($value)) {
                    $nested = $extract($value);
                    if ($nested !== null) {
                        return $nested;
                    }
                }
            }

            foreach ($source as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $nested = $extract($value);
                if ($nested !== null) {
                    return $nested;
                }
            }

            return null;
        };

        return $extract($payload);
    }


    public function getIsMutedAttribute(){
      return $this->status === 'failed' || $this->status === 'canceled'? true: false;
    }

    public function getOrderStatusHtmlAttribute(){
      return view('store-crud::columns.status', ['status' => $this->status, 'context' => 'order', 'type' => 'badge']);
    }

    public function getPayInfoHtmlAttribute() {
      return view('store-crud::columns.pay', ['status' => $this->pay_status, 'payment' => $this->payment, 'muted' => $this->isMuted]);
    }

    public function getDeliveryInfoHtmlAttribute() {
      return view('store-crud::columns.delivery', ['status' => $this->delivery_status, 'delivery' => $this->delivery, 'muted' => $this->isMuted]);
    }


    public function getUserInfoHtmlAttribute() {
      return view('store-crud::columns.user', ['user' => $this->user, 'muted' => $this->isMuted]);
    }

    public function getPriceHtmlAttribute() {
      return view('store-crud::columns.order_price', [
        'price' => $this->price,
        'currency' => store_currency_label($this->currency),
        'muted' => $this->isMuted,
        'order' => $this,
      ]);
    }
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    public function setPromocodeAttribute($value = null) {
      // Checking if promocode data isset in request
      if(empty($value))
        return;
      
      // Checking if promocode really excists in DB and getting it. 
      $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($value)).'%'])->first();
      
      // Check if promocode exists
      if(!$promocode) {
        throw new \Exception('Promocode does not exist.', 404);
      }

      // Check if promocode valid by used times, date and is_active property
      if(!$promocode->isValid) {
        throw new \Exception('Promocode is not valid.', 401);
      }
      
      // Setting promocode info to order's info
      $info = $this->info;
      $info['promocode'] = $promocode;
      $this->info = $info;
    }
    
    /**
     * setProductsRelatedAttribute
     * 
     * Auxiliary field for data processing in Observer, Listeners etc.
     *
     * @param  mixed $v
     * @return void
     */
    public function setProductsRelatedAttribute($v) {
      if (is_array($v)) {
        $this->products_to_synk = $v;
        return;
      }

      if ($v instanceof \Traversable) {
        $this->products_to_synk = iterator_to_array($v, false);
        return;
      }

      $this->products_to_synk = [];
    }
    
    /**
     * setExtrasAttribute
     *
     * @param  array $value
     * @return void
     */
    public function setExtrasAttribute($value) {
      // Getting current info data
      $info_array = $this->info ?? [];

      // New extrat data
      $extras_array = [];

      // For each item 
      foreach ($value as $k => $v) {
        // Undash and set to extras_array by link
        static::undash($extras_array, $k, $v);
      }

      // Merging old and new extras data
      $this->info = array_merge($info_array, $extras_array);
    }
    
    /**
     * undash
     *
     * @param  array $array
     * @param  mixed $key
     * @param  mixed $value
     * @return array
     */
    public static function undash(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('-', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}
