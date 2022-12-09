<?php

namespace ParabellumKoval\Product\app\Http\Controllers\Admin;

use Aimix\Shop\app\Http\Requests\OrderRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

use Carbon\Carbon;

use Aimix\Shop\app\Models\Payment;
use Aimix\Shop\app\Models\Delivery;
use Aimix\Account\app\Models\Usermeta;
use Aimix\Account\app\Models\Transaction;
use App\User;

use Illuminate\Support\Facades\Hash;
use Aimix\Shop\app\Models\Order;
use Aimix\Shop\app\Notifications\OrderCreated;

use Illuminate\Support\Facades\Mail;

/**
 * Class OrderCrudController
 * @package App\Http\Controllers\Admin
 * @property-read CrudPanel $crud
 */
class OrderCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    // use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    
    private $info = [
      'products' => [
        
      ],
      'usermeta' => [
        
      ],
    ];
    
    private $status_options = [
            'new' => 'Новый',
            'pending' => 'Ожидание оплаты',
            'paid' => 'Оплачен',
            'sent' => 'Отправлен',
            'delivered' => 'Доставлен',
            'canceled' => 'Отменён'
          ];
          
    private $current_status;

    public function setup()
    {
        $this->crud->setModel('Aimix\Shop\app\Models\Order');
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/order');
        $this->crud->setEntityNameStrings('заказ', 'Заказы');
        
        $this->current_status = \Request::input('status')? \Request::input('status') : null;
    }

    protected function setupListOperation()
    {
        // TODO: remove setFromDb() and manually define Columns, maybe Filters
        // $this->crud->setFromDb();
        $this->crud->addFilter([
          'name' => 'status',
          'label' => 'Cтатус',
          'type' => 'select2',
        ], function(){
          return $this->status_options;
        }, function($value){
          $this->crud->addClause('where', 'status', $value);
        });
        
        
        $this->crud->addColumn([
          'name' => 'code',
          'label' => 'Номер заказа'
        ]);

        $this->crud->addColumn([
          'name' => 'created_at',
          'label' => 'Дата заказа',
        ]);
        
        $this->crud->addColumn([
          'name' => 'status',
          'label' => 'Статус заказа',
          'type' => 'select_from_array',
          'options' => $this->status_options
        ]);
        
        $this->crud->addColumn([
          'name' => 'price',
          'label' => 'Сумма',
          // 'suffix' => ' руб'
          'prefix' => '$'
        ]);
    }

    // protected function setupCreateOperation()
    // {
    //     $this->crud->setValidation(OrderRequest::class);

    //     // TODO: remove setFromDb() and manually define Fields
    //     $this->crud->setFromDb();
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
          // 'suffix' => ' руб',
          'prefix' => '$',
          'attributes' => [
            'readonly' => true
          ]
        ]);
        
        $this->crud->addField([
          'name' => 'status',
          'label' => 'Статус заказа',
          'type' => 'select2_from_array',
          'options' => $this->status_options
        ]);
    }
    
    protected function setupShowOperation()
    {
        $this->crud->setValidation(OrderRequest::class);

        // TODO: remove setFromDb() and manually define Fields
        // $this->crud->setFromDb();
        $this->crud->addColumn([
          'name' => 'usermeta_id',
          'label' => 'Пользователь'
        ]);
        
        $this->crud->addColumn([
          'name' => 'code',
          'label' => 'Номер заказа'
        ]);

        $this->crud->addColumn([
          'name' => 'created_at',
          'label' => 'Дата заказа'
        ]);
        
        $this->crud->addColumn([
          'name' => 'is_paid',
          'label' => 'Оплачено',
          'type' => 'boolean'
        ]);
        
        $this->crud->addColumn([
          'name' => 'status',
          'label' => 'Статус заказа',
          'type' => 'select_from_array',
          'options' => $this->status_options
        ]);
        
        $this->crud->addColumn([
          'name' => 'price',
          'label' => 'Сумма',
          // 'suffix' => ' руб'
          'prefix' => '$'
        ]);
        
        $this->crud->addColumn([
          'name' => 'delivery_id',
          'label' => 'Способ доставки',
          'type' => 'select',
          'entity' => 'delivery',
          'attribute' => 'name',
          'model' => 'Aimix\Shop\app\Models\Delivery',
        ]);
        
       $this->crud->addColumn([
          'name' => 'payment_id',
          'label' => 'Способ оплаты',
          'type' => 'select',
          'entity' => 'payment',
          'attribute' => 'name',
          'model' => 'Aimix\Shop\app\Models\Payment',
        ]);
        
        $this->crud->addColumn([
          'name' => 'info',
          'label' => 'Информация о заказе',
          'type' => 'order_info_alt'
        ]);
    }
    
    // public function create(OrderRequest $request) {
    //   $payment = Payment::where('name', $request->input('payment'))->first();
    //   $delivery = Delivery::where('name', $request->input('delivery'))->first();
      
      
    //   $products = array_map(function($value) {
    //     return ['amount' => $value['amount']];
    //   }, session()->get('cart'));
      
    //   $info = $request->input();
    //   unset($info['_token']);
      
    //   $info['products'] = session()->get('cart');
      
    //   $price = 0;
      
    //   foreach(session()->pull('cart') as $item) {
    //     $price += $item['price'] * $item['amount'];
    //   }

    //   $order = new Order;
      
    //   $order->code = Carbon::now()->timestamp;
    //   $order->price = $price;
    //   $order->info = $info;
      
    //   $order->payment()->associate($payment);
    //   $order->delivery()->associate($delivery);
      
    //   $order->save();
    //   $order->products()->attach($products);
    //   return redirect('/')->with('message', __('main.order_success'))->with('type', 'success');
    // }

    public function create(OrderRequest $request) {
      $payment = Payment::where('name', $request->input('payment'))->first();
      $delivery = Delivery::where('name', $request->input('delivery'))->first();
      $usermeta = Usermeta::find($request->input('usermeta_id'));
      $products = [];

      if(!$usermeta) {
        $usermeta = new Usermeta;
        $usermeta->firstname = $request->input('firstname');
        $usermeta->lastname = $request->input('lastname');
        $usermeta->patronymic = $request->input('patronymic');
        $usermeta->gender = $request->input('gender');
        $usermeta->birthday = $request->input('birthday');
        $usermeta->telephone = $request->input('telephone');
        $usermeta->email = $request->input('email');
        $usermeta->address = $request->input('address');
        $usermeta->subscription = $request->input('subscription');
        $usermeta->referrer_id = $request->input('referrer_id');
        $usermeta->extras = $request->input('extras');
        $usermeta->save();
      }

      if($request->input('register'))
        $this->createNewUser($usermeta, $request->input('password'));
      
      foreach(session()->get('cart') as $product) {
        $modifications = array_map(function($value) {
          return ['amount' => $value['amount']];
        }, $product);

        foreach($modifications as $key => $modification) {
          $products[$key] = $modification;
        }
      }

      $info = $request->input();
      unset($info['_token']);
      
      $info['products'] = session()->get('cart');
      
      $price = 0;
      
      foreach(session()->pull('cart') as $item) {
        foreach($item as $itemMod) {
          $price += $itemMod['price'] * $itemMod['amount'];
        }
      }

      $order = new Order;
      
      $order->code = substr(Carbon::now()->timestamp, 0, 6);
      $order->price = $price;
      $order->info = $info;
      
      $order->usermeta()->associate($usermeta);
      $order->payment()->associate($payment);
      $order->delivery()->associate($delivery);
      
      $order->save();
      $order->modifications()->attach($products);
      
      if($request->input('bonuses_used')) {
        $transaction = new Transaction;
        
        $transaction->usermeta_id = $usermeta->id;
        $transaction->type = 'bonuses_used';
        $transaction->change = 0 - $request->input('bonuses_used');
        $transaction->balance = $usermeta->bonusBalance + $transaction->change;
        $transaction->order_id = $order->id;
        $transaction->description = 'Bonuses used to pay for the order ' . $order->code;
        
        $transaction->save();
      }

	  // Notify user
      $usermeta->notify(new OrderCreated($order));
      
      // Notify admin
	  Mail::to(config('settings.noty_email'))->send(new \App\Mail\OrderCreatedAdmin($order));
      
      return redirect('/')->with('message', __('main.order_success'))->with('type', 'success')->with('localstorage_remove_ref');
    }

    public function createNewUser($usermeta, $password){
      $user = new User;
      $user->name = $usermeta->firstname;
      $user->email = $usermeta->email;
      $user->password = Hash::make($password);
      
      $user->save();

      $usermeta->user()->associate($user);
  }
    public function cloneOrder($id){
		$order = Order::find($id);
		
		if(!$order)
			return back();
		
		$new_order = $order->replicate()->fill([
		    'status' => 'new',
		    'code' => substr(Carbon::now()->timestamp, -6)
		]);
	
		$new_order->save();
		
		return redirect('/account/order-history')->with('message', 'Order was successfully repeated 😊')->with('type', 'success');
	}
}
