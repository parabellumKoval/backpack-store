<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Listeners\OrderCreatedListener;

use Backpack\Store\app\Events\ProductAttachedToOrder;
use Backpack\Store\app\Listeners\ProductAttachedToOrderListener;

use Backpack\Store\app\Events\AttributeSaved;
use Backpack\Store\app\Listeners\AttributeSavedListener;

use Backpack\Store\app\Events\ProductSaved;
use Backpack\Store\app\Listeners\ProductSavedListener;

use Backpack\Store\app\Events\ProductSaving;
use Backpack\Store\app\Listeners\ProductSavingListener;

use Backpack\Store\app\Events\ProductCreating;
use Backpack\Store\app\Listeners\ProductCreatingListener;

use Backpack\Store\app\Events\PromocodeApplied;
use Backpack\Store\app\Listeners\PromocodeAppliedListener;

use Backpack\Store\app\Events\SourceSaved;
use Backpack\Store\app\Listeners\SourceSavedListener;

use Backpack\Settings\Events\SettingsGroupChanged;
use Backpack\Store\app\Listeners\SettingsGroupChangedListener;


use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Admin\Order as OrderAdmin;
use Backpack\Store\app\Observers\OrderObserver;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
      ProductCreating::class => [
        ProductCreatingListener::class,
      ],
      ProductSaving::class => [
        ProductSavingListener::class,
      ],
      ProductSaved::class => [
        ProductSavedListener::class,
      ],
      AttributeSaved::class => [
        AttributeSavedListener::class,
      ],
      OrderCreated::class => [
        OrderCreatedListener::class,
      ],
      ProductAttachedToOrder::class => [
        ProductAttachedToOrderListener::class,
      ],
      PromocodeApplied::class => [
        PromocodeAppliedListener::class,
      ],
      SourceSaved::class => [
        SourceSavedListener::class,
      ],
      SettingsGroupChanged::class => [
        SettingsGroupChangedListener::class
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

        Order::observe(OrderObserver::class);
        OrderAdmin::observe(OrderObserver::class);
    }
}