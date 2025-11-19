<?php

namespace Backpack\Store\app\Http\Controllers\Admin;


use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\OrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Backpack\CRUD\app\Library\Widget;

use Carbon\Carbon;
use Illuminate\Support\Arr;

use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Mail;

use app\Models\User;
use \Backpack\Store\app\Models\Product;

use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Events\ProductAttachedToOrder;

use Backpack\Store\app\Http\Resources\ProductCartResource;

/**
 * Class OrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class OrderCrudController extends CrudController
{
  use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
  use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation { store as traitStore; }
  use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
  use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
  use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
  use \Backpack\CRUD\app\Http\Controllers\Operations\FetchOperation;

  use \Backpack\Store\app\Http\Controllers\Admin\Traits\BaseCrudTrait;
  use \App\Http\Controllers\Admin\Traits\OrderCrud;
  
  private $status = [];
        
  private $current_status;
  private $ORDER_MODEL = '';
  private $PRODUCT_MODEL = '';

  public function setup()
  {
    $this->crud->setModel(\Settings::get('dress.order.model', 'Backpack\Store\app\Models\Admin\Order'));
    $this->crud->setRoute(config('backpack.base.route_prefix') . '/order');
    $this->crud->setEntityNameStrings(trans('backpack-store::order.single'), trans('backpack-store::order.title'));
    
    $this->ORDER_MODEL = \Settings::get('dress.order.model', 'Backpack\Store\app\Models\Admin\Order');
    $this->PRODUCT_MODEL = \Settings::get('dress.product.model', 'Backpack\Store\app\Models\Product');
    $this->current_status = \Request::input('status')? \Request::input('status') : null;

    $this->setStatusOptions();

    // CURRENT MODEL
    $this->setEntry();

    $this->ORDER_MODEL::created(function($entry) {

      // Sync with Products relation
      foreach($entry->products_to_synk as $key => $product) {
        if(!isset($product->id) || empty($product->id))
          continue;

        $amount = $product->amount ?? 1;
        $entry->products()->attach($product->id, [
          'amount' => $amount,
          'value' => $product->price,
          'currency_code' => $entry->currency_code ?? \Store::countryCurrency($entry->country_code),
          'country_code' => $entry->country_code ?? \Store::country(),
          'supplier_id' => null,
        ]);
      }

      ProductAttachedToOrder::dispatch($entry);
    
    });


    $this->ORDER_MODEL::creating(function($entry) {

      // IF price empty, fill it from products data
      if($entry->price === null) {
        $filtered_products = array_filter($entry->products_to_synk, function($item) {
          return !empty($item->id);
        });

        $plucked_products = Arr::pluck($filtered_products, 'amount', 'id');
        $product_keys = array_keys($plucked_products);

        $products = $this->PRODUCT_MODEL::whereIn('id', $product_keys)->get();

        if(!$products || !$products->count()){
          \Alert::add('error', 'Товары отсутсвуют')->flash();
          return redirect()->back();
        }

        $total_sum = $products->reduce(function($carry, $item) use($plucked_products) {
          return $carry + $item->price * $plucked_products[$item->id];
        }, 0);

        $entry->subtotal = $total_sum;
        $entry->promocode_discount_total = $entry->promocode_discount_total ?? 0;
        $entry->bonus_discount_total = $entry->bonus_discount_total ?? 0;
        $entry->discount_total = round(($entry->promocode_discount_total ?? 0) + ($entry->bonus_discount_total ?? 0), 2);
        $entry->shipping_total = $entry->shipping_total ?? 0;
        $entry->tax_total = $entry->tax_total ?? 0;
        $entry->grand_total = max(0, ($entry->subtotal - $entry->discount_total) + $entry->shipping_total + $entry->tax_total);
        $entry->price = $entry->grand_total;


        // Save products to info field (json)
        foreach($products as $key => $product) {
          $product->amount = $plucked_products[$product->id];
          $info = $entry->info;
          $info['products'][$key] = new ProductCartResource($product);
          $info['bonusesUsed'] = $info['bonusesUsed'] ?? 0;
          $existingBonuses = $info['bonuses'] ?? [];
          $currencyCode = $entry->currency_code ?? \Store::countryCurrency($entry->country_code);
          $bonusFiat = $entry->bonus_discount_total ?? $info['bonusesUsed'];
          $info['bonuses'] = array_merge([
            'points' => 0,
            'fiat_amount' => $bonusFiat,
            'fiat_currency' => $currencyCode,
            'order_currency' => $currencyCode,
            'wallet_currency' => $existingBonuses['wallet_currency'] ?? null,
            'refunded' => $existingBonuses['refunded'] ?? false,
            'reference_id' => $existingBonuses['reference_id'] ?? null,
          ], $existingBonuses);
          $info['bonusesUsed'] = $bonusFiat;
          $entry->info = $info;
        }
      }

      // Generate random code
      $entry->code = random_int(100000, 999999);
    });

    // Trait
    $this->setupOperation();
  }

  private function setStatusOptions() {
    $status_base = [
      'order' => \Settings::get('dress.order.status.values', []),
      'pay' => \Settings::get('dress.order.pay_status.values', []),
      'delivery' => \Settings::get('dress.order.delivery_status.values', [])
    ];

    foreach($status_base as $key => $status){
      $statuses = array_map(function($value) use ($key) {
        return array($value => __('backpack-store::shop.' . $key . '_status.' . $value));
      }, $status_base[$key]);

      $status_base[$key] = array_reduce($statuses, 'array_merge', array());
    }

    $this->status = $status_base;
  }
  
  protected function setupListOperation()
  {
      $this->crud->enableDetailsRow();
      $this->crud->setDetailsRowView('store-crud::details.order_products');

      $this->crud->addFilter([
        'name' => 'status',
        'label' => trans('backpack-store::order.fields.status'),
        'type' => 'select2',
      ], function(){
        return $this->status['order'];
      }, function($value){
        $this->crud->addClause('where', 'status', $value);
      });

      $this->crud->addFilter([
        'name' => 'pay_status',
        'label' => trans('backpack-store::order.fields.pay_status'),
        'type' => 'select2',
      ], function(){
        return $this->status['pay'];
      }, function($value){
        $this->crud->addClause('where', 'pay_status', $value);
      });

      $this->crud->addFilter([
        'name' => 'delivery_status',
        'label' => trans('backpack-store::order.fields.delivery.status'),
        'type' => 'select2',
      ], function(){
        return $this->status['delivery'];
      }, function($value){
        $this->crud->addClause('where', 'delivery_status', $value);
      });
      

      if(\Store::isMulti()) {
        
        CRUD::addFilter([
            'name'  => 'country_code',
            'type'  => 'dropdown',
            'label' => 'Страна',
        ], function () {
            // Отрисуем словарь стран из настроек
            $list = [];
            $countries = \Store::countryOptions();
            foreach ($countries as $code => $name) {
                $list[$code] = strtoupper($name);
            }
            return $list;
        }, function ($value) {
            CRUD::addClause('where', 'country_code', $value);
        });

        // $this->setSimpleCountryWidget();
        $this->setStatisticsWidget();


        if ($tab = request()->get('country_code')) {
            if ($tab !== 'all') {
                CRUD::addClause('where', 'country_code', $tab);
            }
        }
      }



      $this->crud->addColumn([
        'name' => 'code',
        'label' => '#️⃣'
      ]);

      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => '🗓',
      ]);
      
      if(\Store::isMulti()) {
        $this->crud->addColumn([
          'name' => 'country_code',
          'label' => 'Страна',
          'type' => 'country_flag_label',
        ]);
      }

      $this->crud->addColumn([
        'name' => 'orderStatusHtml',
        'label' => 'Статус',
        'escaped' => false,
        'limit' => 5500,
      ]);
      
      $this->crud->addColumn([
        'name' => 'payInfoHtml',
        'label' => 'Доставка',
        'escaped' => false,
        'limit' => 5500,
      ]);
      
      $this->crud->addColumn([
        'name' => 'deliveryInfoHtml',
        'label' => 'Оплата',
        'escaped' => false,
        'limit' => 5500,
      ]);

      $this->crud->addColumn([
        'name' => 'userInfoHtml',
        'label' => 'Клиент',
        'escaped' => false,
        'limit' => 5500,
      ]);

      $this->crud->addColumn([
        'name' => 'priceHtml',
        'label' => '💵',
        'escaped' => false,
        'limit' => 5500,
      ]);

      $this->crud->addButtonFromView('line', 'invoice_preview', 'invoice_preview', 'end');
      $this->crud->addButtonFromView('line', 'invoice_download', 'invoice_download', 'end');
      $this->crud->addButtonFromView('line', 'invoice_qr', 'invoice_qr', 'end');

      // $this->crud->addColumn([
      //   'name' => 'status',
      //   'label' => '✅',
      //   'type' => 'select_from_array',
      //   'options' => $this->status['order']
      // ]);

      // $this->crud->addColumn([
      //   'name' => 'pay_status',
      //   'label' => '💳',
      //   'type' => 'select_from_array',
      //   'options' => $this->status['pay']
      // ]);
      
      // $this->crud->addColumn([
      //   'name' => 'delivery_status',
      //   'label' => '🛵',
      //   'type' => 'select_from_array',
      //   'options' => $this->status['delivery']
      // ]);

      // TRAIT
      $this->listOperation();
  }

  protected function setSimpleCountryWidget() {

        Widget::add([
            'type'    => 'view',
            'view'    => 'crud::widgets.country-tabs',
            'wrapper' => ['class' => 'mb-2'], // отступ снизу
            'content' => [
                'active'    => request()->get('country_code', 'all'),
                'countries' => \Store::countryOptions(),
                'param'     => 'country_code', // имя query param
                'filterKey' => 'country_code', // ключ реального фильтра
            ],
        ]);

  }

  protected function setStatisticsWidget() {
     // соберём стату (кэшируем на 60 сек, чтобы не грузить БД каждый рефреш)
    $stats = \Cache::remember('orders.country.stats', 60, function () {
        return $this->ORDER_MODEL::query()
        ->selectRaw("COALESCE(country_code,'') as country_code,
                     COUNT(*) as total,
                     SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count")
        ->groupBy('country_code')
        ->get()
        ->keyBy('country_code');
    });

    // хотим показать даже страны без заказов — подтащим список из настроек
    $options = \Store::countryOptions();

    // Собираем карточки в порядке, как вернул Store::countryOptions()
    $cards = [];
    foreach ($options as $code => $name) {
        $row = $stats[$code] ?? (object)['country_code'=>$code,'total'=>0,'new_count'=>0];
        $cards[] = [
            'code' => $code,
            'name' => $name,
            'total'=> (int) $row->total,
            'new'  => (int) $row->new_count,
        ];
    }

    $totals = [
        'total' => array_sum(array_column($cards,'total')),
        'new'   => array_sum(array_column($cards,'new')),
    ];

    array_unshift($cards, [
        'code'  => 'all',
        'name'  => 'Все',
        'total' => (int) $totals['total'],
        'new'   => (int) $totals['new'],
    ]);

    // втыкаем виджет над таблицей
    Widget::add([
        'type'    => 'view',
        'view'    => 'crud::widgets.orders-country-cards',
        'wrapper' => ['class' => 'mb-2'],
        'content' => [
            'cards'       => $cards,
            'listUrl'     => url(request()->path()),
            'param'       => 'country_code',  // имя query-параметра
            'filterKey'   => 'country_code', // ключ реального фильтра
            'active'      => request('country_code', 'all'),
        ],
    ]);
  }

  protected function setupCreateOperation()
  {
    $this->crud->setValidation(OrderRequest::class);

    $this->crud->addField([
      'name' => 'created_at',
      'label' => trans('backpack-store::order.fields.created_at'),
      'type' => 'datetime_picker',
      'hint' => trans('backpack-store::order.fields.hints.price'),
      'wrapper' => [ 
        'class' => 'form-group col-md-4'
      ]
    ]);

    $this->crud->addField([
      'name' => 'status',
      'label' => trans('backpack-store::order.fields.status'),
      'type' => 'select2_from_array',
      'options' => $this->status['order'],
      'wrapper' => [ 
        'class' => 'form-group col-md-4'
      ]
    ]);

    if(\Store::isMulti()) {
      $this->crud->addField([
        'name' => 'country_code',
        'label' => trans('backpack-store::order.fields.country'),
        'type' => 'select2_from_array',
        'options' => \Store::countryOptions(),
        'wrapper' => [ 
          'class' => 'form-group col-md-4'
        ]
      ]);
    }

    $this->crud->addField([
      'name'  => 'separator_01',
      'type'  => 'custom_html',
      'value' => '<hr>'
    ]);

    $this->crud->addField([
      'name'  => 'caption_01',
      'type'  => 'custom_html',
      'value' => '<h5>' . trans('backpack-store::order.fields.products.title') . '</h5>'
    ]);

    $this->crud->addField([
      'name' => 'productsRelated',
      'label' => trans('backpack-store::order.fields.products.title'),
      'type'  => 'repeatable',
      'fields' => [
        [
            'name'    => 'id',
            'type'      => 'select2_from_ajax',
            'label'   => 'Товар',
            'model'     => $this->PRODUCT_MODEL,
            'attribute' => 'name',
            'entity' => 'products',
            'data_source' => url("/admin/api/product"),
            'wrapper' => ['class' => 'form-group col-md-10'],
            'placeholder' => "Выберите товар",
            'minimum_input_length' => 2
        ],  
        [
            'name'    => 'amount',
            'type'    => 'number',
            'label'   => 'Кол-во',
            'default' => 1,
            'wrapper' => ['class' => 'form-group col-md-2'],
            'attributes' => [
              'min' => 1,
              'required' => true
            ]
        ]
      ]

    ]);

    $this->crud->addField([
      'name' => 'price',
      'label' => trans('backpack-store::order.fields.price'),
      'hint' => trans('backpack-store::order.fields.hints.price'),
      'wrapper' => [ 
        'class' => 'form-group col-md-2'
      ]
    ]);

    $this->crud->addField([
      'name' => 'currency_code',
      'label' => trans('backpack-store::order.fields.currency'),
      'type' => 'select2_from_array',
      'options' => \Store::currencyOptions(),
      'wrapper' => [ 
        'class' => 'form-group col-md-2'
      ]
    ]);
    
    $this->crud->addField([
      'name' => 'pay_status',
      'label' => trans('backpack-store::order.fields.pay_status'),
      'type' => 'select2_from_array',
      'options' => $this->status['pay'],
      'wrapper' => [ 
        'class' => 'form-group col-md-4'
      ]
    ]);
    
    $this->crud->addField([
      'name' => 'payment-method',
      'label' => trans('backpack-store::order.fields.payment_method'),
      'fake'     => true,
      'store_in' => 'extras',
      'type' => 'select2_from_array',
      'default' => 'cash',
      'options' => [
        'cash' => trans('backpack-store::order.fields.payment_methods.cash'),
        'liqpay' => trans('backpack-store::order.fields.payment_methods.liqpay')
      ],
      'wrapper' => [ 
        'class' => 'form-group col-md-4'
      ]
    ]);

    // USER
    $this->crud->addField([
      'name'  => 'separator_0',
      'type'  => 'custom_html',
      'value' => '<hr>'
    ]);

    $this->crud->addField([
      'name'  => 'caption_0',
      'type'  => 'custom_html',
      'value' => '<h5>' . trans('backpack-store::order.fields.customer.title') . '</h5>'
    ]);

    $this->crud->addField([
        'name' => 'user-firstname',
        'label' => trans('backpack-store::order.fields.customer.firstname'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);
    $this->crud->addField([
        'name' => 'user-lastname',
        'label' => trans('backpack-store::order.fields.customer.lastname'),
        'type' => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);
    $this->crud->addField([
        'name' => 'user-email',
        'label' => trans('backpack-store::order.fields.customer.email'),
        'type'  => 'email',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);
    $this->crud->addField([
        'name' => 'user-phone',
        'label' => trans('backpack-store::order.fields.customer.phone'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);

    // DELIVERY
    $this->crud->addField([
      'name'  => 'separator_1',
      'type'  => 'custom_html',
      'value' => '<hr>'
    ]);

    $this->crud->addField([
      'name'  => 'caption_1',
      'type'  => 'custom_html',
      'value' => '<h5>' . trans('backpack-store::order.fields.delivery.title') . '</h5>'
    ]);

    
    $this->crud->addField([
      'name' => 'delivery_status',
      'label' => trans('backpack-store::order.fields.delivery.status'),
      'type' => 'select2_from_array',
      'options' => $this->status['delivery'],
      'wrapper' => [ 
        'class' => 'form-group col-md-3'
      ]
    ]);

    $this->crud->addField([
        'name' => 'delivery-method',
        'label' => trans('backpack-store::order.fields.delivery.method'),
        'fake'     => true,
        'store_in' => 'extras',
        'type' => 'select2_from_array',
        'default' => 'warehouse',
        'options' => [
          'warehouse' => trans('backpack-store::order.fields.delivery.methods.warehouse'),
          'address' => trans('backpack-store::order.fields.delivery.methods.address'),
          'pickup' => trans('backpack-store::order.fields.delivery.methods.pickup')
        ],
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);

    $this->crud->addField([
        'name' => 'delivery-warehouse',
        'label' => trans('backpack-store::order.fields.delivery.warehouse'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-6'
        ]
    ]);

    $this->crud->addField([
        'name' => 'delivery-city',
        'label' => trans('backpack-store::order.fields.delivery.city'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-3'
        ]
    ]);

    $this->crud->addField([
        'name' => 'delivery-address',
        'label' => trans('backpack-store::order.fields.delivery.address'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-7'
        ]
    ]);
    
    $this->crud->addField([
        'name' => 'delivery-zip',
        'label' => trans('backpack-store::order.fields.delivery.zip'),
        'type'  => 'text',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-2'
        ]
    ]);

    $this->crud->addField([
        'name' => 'delivery-comment',
        'label' => trans('backpack-store::order.fields.delivery.comment'),
        'type'  => 'textarea',
        'fake'     => true,
        'store_in' => 'extras',
        'wrapper' => [ 
          'class' => 'form-group col-md-12'
        ]
    ]);

    // TRAIT
    $this->createOperation();

  }

  // public function store()
  // {
  //   $response = $this->traitStore();
  //   return $response;
  // }

  protected function setupUpdateOperation()
  {
      //$this->setupCreateOperation();
      
      $this->crud->addField([
        'name' => 'code',
        'label' => 'Номер заказа',
        'attributes' => [
          'readonly' => true
        ]
      ]);
      
      $this->crud->addField([
        'name' => 'price',
        'label' => 'Сумма заказа',
        'prefix' => $this->entry->currency ?? \Settings::get('dress.store.currency.symbol'),
        'attributes' => [
          'readonly' => true
        ]
      ]);
      
      $this->crud->addField([
        'name' => 'status',
        'label' => 'Статус заказа',
        'type' => 'select2_from_array',
        'options' => $this->status['order'],
        'allows_null' => false,
      ]);
      
      $this->crud->addField([
        'name' => 'pay_status',
        'label' => 'Статус оплаты',
        'type' => 'select2_from_array',
        'options' => $this->status['pay'],
        'allows_null' => false,
      ]);
      
      $this->crud->addField([
        'name' => 'delivery_status',
        'label' => 'Статус доставки',
        'type' => 'select2_from_array',
        'options' => $this->status['delivery'],
        'allows_null' => false,
      ]);
  }
  
  protected function setupShowOperation()
  {
      $this->crud->set('show.setFromDb', false);

      CRUD::addButtonFromView('top', 'invoice_preview', 'invoice_preview', 'beginning');
      CRUD::addButtonFromView('top', 'invoice_download', 'invoice_download', 'beginning');
      CRUD::addButtonFromView('top', 'invoice_qr', 'invoice_qr', 'beginning');

      $this->crud->addColumn([
        'name' => 'code',
        'label' => trans('backpack-store::order.fields.code')
      ]);

      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => trans('backpack-store::order.fields.created_at')
      ]);
      
      $this->crud->addColumn([
        'name' => 'status',
        'label' => trans('backpack-store::order.fields.status'),
        'type' => 'select_from_array',
        'options' => $this->status['order']
      ]);
      
      $this->crud->addColumn([
        'name' => 'pay_status',
        'label' => trans('backpack-store::order.fields.pay_status'),
        'type' => 'select_from_array',
        'options' => $this->status['pay']
      ]);
      
      $this->crud->addColumn([
        'name' => 'delivery_status',
        'label' => trans('backpack-store::order.fields.delivery.status'),
        'type' => 'select_from_array',
        'options' => $this->status['delivery']
      ]);
      
      $this->crud->addColumn([
        'name' => 'info',
        'label' => 'Информация о заказе',
        'type' => 'order_info'
      ]);
  }


  protected function fetchProduct()
  {
      return $this->fetch([
        'model' => \Backpack\Store\app\Models\Product::class, // required
        'searchable_attributes' => ['name', 'code', 'slug'],
        'paginate' => 50
      ]);
  }



}
