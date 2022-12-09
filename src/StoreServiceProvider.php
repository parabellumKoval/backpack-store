<?php

namespace Backpack\Store;

// use Spatie\LaravelPackageTools\Package;
// use Spatie\LaravelPackageTools\PackageServiceProvider;
// use ParabellumKoval\Product\Commands\ProductCommand;
use \Illuminate\Support\ServiceProvider;

class StoreServiceProvider extends ServiceProvider
{
  public function boot()
  {
    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

    // Routes
    $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
  
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
