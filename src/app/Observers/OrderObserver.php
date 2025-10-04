<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Models\Order;

use Backpack\Store\app\Events\OrderСompleted;
use Backpack\Store\app\Events\OrderRejected;
use Backpack\Store\app\Events\OrderDeleted;

class OrderObserver
{
  public function created(Order $order): void
  {
  }

  public function updated(Order $order): void
  {
      if ($order->wasChanged('status')) {
          if ($order->status === 'completed') {
              event(new OrderСompleted($order));
          } else {
              event(new OrderRejected($order));
          }
      }
  }

  public function deleting(Order $order) {
      event(new OrderDeleted($order));
  }
}