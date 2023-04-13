<?php

namespace Backpack\Store;

// use Spatie\LaravelPackageTools\Package;
// use Spatie\LaravelPackageTools\PackageServiceProvider;
// use ParabellumKoval\Product\Commands\ProductCommand;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{

  const CONFIG_PATH = __DIR__ . '/config/store.php';

  public function boot()
  {
    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

    // Routes
    $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/product.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/category.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/order.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/cart.php');
    

    $this->publishes([
      self::CONFIG_PATH => config_path('/backpack/store.php'),
    ], 'config');
    
    $this->publishes([
        __DIR__.'/resources/views' => resource_path('views'),
        __DIR__.'/resources/lang' => resource_path('lang'),
    ], 'views');

    $this->publishes([
        __DIR__.'/database/migrations' => database_path('migrations'),
    ], 'migrations');

    $this->publishes([
        __DIR__.'/routes' => base_path('routes')
    ], 'routes');

  }

    // public function configurePackage(Package $package): void
    // {
    //     /*
    //      * This class is a Package Service Provider
    //      *
    //      * More info: https://github.com/spatie/laravel-package-tools
    //      */
    //     $package
    //         ->name('products-for-backpack')
    //         ->hasConfigFile()
    //         ->hasViews()
    //         ->hasMigration('create_products-for-backpack_table')
    //         ->hasCommand(ProductCommand::class);
    // }
}
