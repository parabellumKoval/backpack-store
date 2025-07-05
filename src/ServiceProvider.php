<?php

namespace Backpack\Store;

use Backpack\Store\app\Providers\EventServiceProvider;
use Backpack\Store\app\Console\Commands\XmlSource;
use Backpack\Store\app\Console\Commands\AttributesTransform;
use Backpack\Store\app\Console\Commands\XmlCorrectInStock;
use Backpack\Store\app\Console\Commands\ImportGoogleTaxonomy;
use Backpack\Store\app\Console\Commands\CatalogCache;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{

  const CONFIG_PATH = __DIR__ . '/config/store.php';

  public function boot()
  {
    // Добавляем кастомный путь для представлений Backpack
    View::addNamespace('store-crud', [
        resource_path('views/vendor/backpack/crud'),
        __DIR__.'/resources/views/crud',
    ]);

    // Load translations
    $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'backpack-store');

    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

    // Routes
    $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/product.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/category.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/order.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/cart.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/promocode.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/attribute.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/brand.php');
    

    $this->publishes([
      self::CONFIG_PATH => config_path('/backpack/store.php'),
      // __DIR__ . '/config/auth.php' => config_path('/auth.php'),
    ], 'config');
    
    $this->publishes([
        __DIR__.'/resources/views' => resource_path('views'),
    ], 'views');
    
    $this->publishes([
        __DIR__.'/resources/lang' => resource_path('lang'),
    ], 'langs');

    $this->publishes([
        __DIR__.'/database/migrations' => database_path('migrations'),
    ], 'migrations');

    $this->publishes([
        __DIR__.'/routes' => base_path('routes')
    ], 'routes');

    // Assets 
    $packagePublicPath = __DIR__.'/public';
    // $appPublicPath = base_path('public/packages/backpack/store');
    $appPublicPath = public_path('packages/backpack/store');

    // // Удаляем старый симлинк, файл или папку, если они существуют
    // if (is_link($appPublicPath)) {
    //     unlink($appPublicPath); // Если это символическая ссылка
    // } elseif (file_exists($appPublicPath)) {
    //     if (is_dir($appPublicPath)) {
    //         File::deleteDirectory($appPublicPath); // Если это папка
    //     } else {
    //         File::delete($appPublicPath); // Если это файл
    //     }
    // }

    // // Создаём новый симлинк
    // symlink(realpath($packagePublicPath), $appPublicPath);

    $this->publishes([
        $packagePublicPath => $appPublicPath,
    ], 'public');


    // $this->publishes([
    //     __DIR__.'/app/Traits/Controllers/Admin' => base_path('app/Http/Controllers/Admin/Traits')
    // ], 'models');

    $this->publishes([
        __DIR__.'/app/Traits/Controllers/Admin' => base_path('app/Http/Controllers/Admin/Traits'),
      __DIR__.'/app/Traits/Models' => base_path('app/Http/Models/Traits')
    ], 'traits');

    // Comands
    if ($this->app->runningInConsole()) {
      $this->commands([
        XmlSource::class,
        AttributesTransform::class,
        XmlCorrectInStock::class,
        ImportGoogleTaxonomy::class,
        CatalogCache::class,
      ]);
    }
  }

  public function register()
  {
    $this->app->register(EventServiceProvider::class);

    $this->mergeConfigFrom(self::CONFIG_PATH, 'backpack.store');
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
