<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Support\ServiceProvider;
use Backpack\Store\app\Services\SettingsService;

class SettingsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('store.settings', function () {
            return new SettingsService();
        });
    }

    public function boot()
    {
        // Миграции, представления, маршруты
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'store');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
