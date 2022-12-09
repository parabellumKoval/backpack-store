<?php

namespace Aimix\Shop\app\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Aimix\Shop\app\Models\Product;
use Aimix\Shop\app\Models\Delivery;
use Aimix\Shop\app\Models\Payment;

class CheckoutController extends \App\Http\Controllers\Controller
{
	//
	// PROPERTIES 
	//
  
  public function cart(Request $request) {
      if(session()->has('cart') && count(session()->get('cart')))
        return view('checkout.cart');
      
      return redirect('/')->with('message', __('main.cart_empty'))->with('type', 'error');
    
  }
  
  public function update(Request $request) {
    session()->put('cart', $request->data);
  }
  
  public function checkout(Request $request) {
    $deliveries = Delivery::active()->orderBy('lft')->orderBy('created_at', 'desc')->get();
    $payments = Payment::active()->orderBy('lft')->orderBy('created_at', 'desc')->get();
    $bonusBalance = \Auth::user()? \Auth::user()->usermeta->bonusBalance : null;
    
    return view('checkout.checkout')->with('deliveries', $deliveries)->with('payments', $payments)->with('bonus_balance', $bonusBalance);
  }
}