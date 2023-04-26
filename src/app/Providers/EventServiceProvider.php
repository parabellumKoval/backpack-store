<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Listeners\OrderCreatedListener;

use Backpack\Store\app\Events\ProductAttachedToOrder;
use Backpack\Store\app\Listeners\ProductAttachedToOrderListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
      OrderCreated::class => [
        OrderCreatedListener::class,
      ],
      ProductAttachedToOrder::class => [
        ProductAttachedToOrderListener::class,
      ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}