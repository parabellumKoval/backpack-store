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
    ], 'views');

    $this->publishes([
        __DIR__.'/database/migrations' => resource_path('database/migrations'),
    ], 'migrations');

    $this->publishes([
        __DIR__.'/routes/backpack/routes.php' => resource_path('/routes/backpack/store/backpack.php'),
        __DIR__.'/routes/api/product.php' => resource_path('/routes/backpack/store/product.php'),
        __DIR__.'/routes/api/order.php' => resource_path('/routes/backpack/store/order.php'),
        __DIR__.'/routes/api/cart.php' => resource_path('/routes/backpack/store/cart.php'),
        __DIR__.'/routes/api/category.php' => resource_path('/routes/backpack/store/category.php'),
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
