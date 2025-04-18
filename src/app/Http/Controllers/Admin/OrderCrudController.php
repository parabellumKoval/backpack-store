<?php

namespace Backpack\Store\app\Http\Controllers\Admin;


use Illuminate\Http\Request;
use Backpack\Store\app\Http\Requests\OrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

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

  use \App\Http\Controllers\Admin\Traits\OrderCrud;
  
  private $status = [];
        
  private $current_status;
  private $ORDER_MODEL = '';
  private $PRODUCT_MODEL = '';

  public function setup()
  {
    $this->crud->setModel(config('backpack.store.order_model', 'Backpack\Store\app\Models\Admin\Order'));
    $this->crud->setRoute(config('backpack.base.route_prefix') . '/order');
    $this->crud->setEntityNameStrings(trans('backpack-store::order.single'), trans('backpack-store::order.title'));
    
    $this->ORDER_MODEL = config('backpack.store.order_model', 'Backpack\Store\app\Models\Admin\Order');
    $this->PRODUCT_MODEL = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
    $this->current_status = \Request::input('status')? \Request::input('status') : null;

    $this->setStatusOptions();

    $this->ORDER_MODEL::created(function($entry) {

      // Sync with Products relation
      foreach($entry->products_to_synk as $key => $product) {
        if(!isset($product->id) || empty($product->id))
          continue;

        $amount = $product->amount ?? 1;
        $entry->products()->attach($product->id, ['amount' => $amount]);
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
        
        $entry->price = $total_sum;


        // Save products to info field (json)
        foreach($products as $key => $product) {
          $product->amount = $plucked_products[$product->id];
          $info = $entry->info;
          $info['products'][$key] = new ProductCartResource($product);
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
      'order' => config('backpack.store.order.status.values', []),
      'pay' => config('backpack.store.order.pay_status.values', []),
      'delivery' => config('backpack.store.order.delivery_status.values', [])
    ];

    foreach($status_base as $key => $status){
      $statuses = array_map(function($value) use ($key) {
        return array($value => __('shop.' . $key . '_status.' . $value));
      }, $status_base[$key]);

      $status_base[$key] = array_reduce($statuses, 'array_merge', array());
    }

    $this->status = $status_base;
  }
  
  protected function setupListOperation()
  {
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
      
      
      $this->crud->addColumn([
        'name' => 'code',
        'label' => '#️⃣'
      ]);

      $this->crud->addColumn([
        'name' => 'created_at',
        'label' => '🗓',
      ]);
      
      $this->crud->addColumn([
        'name' => 'status',
        'label' => '✅',
        'type' => 'select_from_array',
        'options' => $this->status['order']
      ]);
      
      $this->crud->addColumn([
        'name' => 'pay_status',
        'label' => '💳',
        'type' => 'select_from_array',
        'options' => $this->status['pay']
      ]);
      
      $this->crud->addColumn([
        'name' => 'delivery_status',
        'label' => '🛵',
        'type' => 'select_from_array',
        'options' => $this->status['delivery']
      ]);
      
      $this->crud->addColumn([
        'name' => 'price',
        'label' => '💵',
        'prefix' => config('backpack.store.currency.symbol')
      ]);

      // TRAIT
      $this->listOperation();
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
        'class' => 'form-group col-md-8'
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
      'prefix' => config('backpack.store.currency.symbol'),
      'hint' => trans('backpack-store::order.fields.hints.price'),
      'wrapper' => [ 
        'class' => 'form-group col-md-4'
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
        'prefix' => config('backpack.store.currency.symbol'),
        'attributes' => [
          'readonly' => true
        ]
      ]);
      
      $this->crud->addField([
        'name' => 'status',
        'label' => 'Статус заказа',
        'type' => 'select2_from_array',
        'options' => $this->status['order']
      ]);
      
      $this->crud->addField([
        'name' => 'pay_status',
        'label' => 'Статус оплаты',
        'type' => 'select2_from_array',
        'options' => $this->status['pay']
      ]);
      
      $this->crud->addField([
        'name' => 'delivery_status',
        'label' => 'Статус доставки',
        'type' => 'select2_from_array',
        'options' => $this->status['delivery']
      ]);
  }
  
  protected function setupShowOperation()
  {
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

  // public static function mssql_escape($unsafe_str) 
  // {
  //     if (get_magic_quotes_gpc())
  //     {
  //         $unsafe_str = stripslashes($unsafe_str);
  //     }
  //     return $escaped_str = str_replace("'", "''", $unsafe_str);
  // }

}
