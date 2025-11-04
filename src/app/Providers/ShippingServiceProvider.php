<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Support\ServiceProvider;
use Backpack\Store\app\Services\Shipping\ShippingCalculator;
use Backpack\Store\app\Contracts\ShippingProviderInterface;
use Backpack\Store\app\Services\Shipping\Providers\PacketaProvider;
use Backpack\Store\app\Services\Shipping\Providers\NovaPoshtaProvider;

class ShippingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Зарегистрируем провайдеров как массив
        $this->app->bind('store.shipping.providers', function ($app) {
            return [
                $app->make(PacketaProvider::class),
                $app->make(NovaPoshtaProvider::class),
            ];
        });

        // Сам калькулятор
        $this->app->singleton(ShippingCalculator::class, function ($app) {
            /** @var array<ShippingProviderInterface> $providers */
            $providers = $app->make('store.shipping.providers');
            return new ShippingCalculator($providers);
        });
    }
}
