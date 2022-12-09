<?php

namespace Aimix\Shop\app\Observers;

use Aimix\Shop\app\Models\Order;
use Aimix\Account\app\Models\Transaction;
use Aimix\Account\app\Models\Usermeta;
use Aimix\Account\app\Notifications\CashbackBonus;
use Aimix\Account\app\Notifications\ReferralBonus;

class OrderObserver
{
    public function deleting(Order $order) {
      $order->products()->detach();
      $order->modifications()->detach();
    }

    public function updated(Order $order) {
      if(count($order->transactions) || ($order->status != 'sent' && $order->status != 'paid' && $order->status != 'delivered') )
        return;

      if(config('aimix.account.enable_referral_system') && $order->usermeta->referrer_id)
        $this->createReferralTransactions($order);

      if(config('aimix.account.enable_cashback'))
        $this->createCashbackTransactions($order);
    }

    public function createReferralTransactions($order) {
      $referrer = $order->usermeta;
      $bonuses = config('aimix.account.referral_bonuses');

      for($i = 0; $i < config('aimix.account.referral_levels'); $i++) {
        $referrer = $referrer->referrer;

        if(!$referrer)
          return;

        $currentBalance = $referrer->bonus_balance;
        $bonus = $bonuses[$i]? $bonuses[$i] : $bonuses[count($bonuses - 1)];

        $transaction = new Transaction;
        $transaction->type = 'bonus';
        $transaction->description = $bonus . '% bonus from level ' . ($i + 1) . ' referral';
        $transaction->order_id = $order->id;
        $transaction->usermeta_id = $referrer->id;
        $transaction->change = $order->price * $bonus / 100;
        $transaction->balance = $currentBalance + $transaction->change;
        $transaction->save();

        $referrer->notify(new ReferralBonus($transaction));
      }
    }

    public function createCashbackTransactions($order) {
      $cashback_value = config('aimix.account.cashback_value');
      $currentBalance = $order->usermeta->bonus_balance;

      $transaction = new Transaction;
      $transaction->type = 'cashback';
      $transaction->description = $cashback_value . '% cashback from order ' . $order->code;
      $transaction->order_id = $order->id;
      $transaction->usermeta_id = $order->usermeta_id;
      $transaction->change = $order->price * $cashback_value / 100;
      $transaction->balance = $currentBalance + $transaction->change;
      $transaction->save();
      
      $order->usermeta->notify(new CashbackBonus($transaction));
    }
}