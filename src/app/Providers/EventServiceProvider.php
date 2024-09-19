<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Listeners\OrderCreatedListener;

use Backpack\Store\app\Events\ProductAttachedToOrder;
use Backpack\Store\app\Listeners\ProductAttachedToOrderListener;

use Backpack\Store\app\Events\PromocodeApplied;
use Backpack\Store\app\Listeners\PromocodeAppliedListener;

use \Backpack\Store\app\Models\Order;
use \Backpack\Store\app\Observers\OrderObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
      OrderCreated::class => [
        OrderCreatedListener::class,
      ],
      ProductAttachedToOrder::class => [
        ProductAttachedToOrderListener::class,
      ],
      PromocodeApplied::class => [
        PromocodeAppliedListener::class,
      ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {

        $order_model = config('backpack.store.order_model', 'Backpack\Store\app\Models\Admin\Order');
        $order_model::observe(OrderObserver::class);


        parent::boot();
    }
}