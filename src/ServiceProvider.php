<?php

namespace Backpack\Store;

use Illuminate\Foundation\AliasLoader;

use Backpack\Store\app\Providers\EventServiceProvider;
use Backpack\Store\app\Providers\SettingsServiceProvider;

use Backpack\Store\app\Console\Commands\XmlSource;
use Backpack\Store\app\Console\Commands\AttributesTransform;
use Backpack\Store\app\Console\Commands\XmlCorrectInStock;
use Backpack\Store\app\Console\Commands\ImportGoogleTaxonomy;
use Backpack\Store\app\Console\Commands\CatalogCache;
use Backpack\Store\app\Console\Commands\ProductPreprocessing;

use Backpack\Store\app\Contracts\ProductService;
use Backpack\Store\app\Contracts\Admin\SupplierFormStrategy;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
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
    $this->loadRoutesFrom(__DIR__.'/routes/backpack/settings.php');

    $this->loadRoutesFrom(__DIR__.'/routes/api/product.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/category.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/order.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/cart.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/promocode.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/attribute.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/brand.php');
    

    $this->publishes([
      __DIR__ . '/config/modifications.php' => config_path('/backpack-store/modifications.php'),
      __DIR__ . '/config/multistore.php' => config_path('/backpack-store/multistore.php'),
      __DIR__ . '/config/product_quality.php' => config_path('/backpack-store/product_quality.php'),

      __DIR__ . '/config/store.php' => config_path('/backpack/store.php'),
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
        ProductPreprocessing::class
      ]);
    }

    $this->addStyles();
    $this->registerFacadeAlias();

    // Resolve dependencies for multistore (regions and currency) and single mode 
    $this->resolveMode();
  }

  private function resolveMode() {
    $isMultistore = \Store::isMulti();
    $isVertical = \Store::isModVertical();

    $this->app->scoped(\Backpack\Store\app\Services\StoreContext::class, function ($app) {
        return $app->make(\Backpack\Store\app\Services\Resolvers\StoreContextResolver::class)->resolve();
    });

    // Резолвер можно сделать singleton — он статичен
    $this->app->singleton(\Backpack\Store\app\Services\Resolvers\StoreContextResolver::class);

    $namespace = $isVertical? '\Backpack\Store\app\Services\Variant\Vertical': '\Backpack\Store\app\Services\Variant\Horizontal';
    $this->app->bind(\Backpack\Store\app\Contracts\VariantAvailability::class, "{$namespace}\VariantAvailability");

    $namespace = $isMultistore? '\Backpack\Store\app\Services\Region\Multi': '\Backpack\Store\app\Services\Region\Single';
    $this->app->bind(\Backpack\Store\app\Contracts\SupplierFilter::class, "{$namespace}\SupplierFilter");
    $this->app->bind(SupplierFormStrategy::class, "{$namespace}\Admin\SupplierFormStrategy");
    $this->app->bind(ProductService::class, "{$namespace}\ProductService");


  }

  private function addStyles() {
    $styles = config('backpack.base.styles', []);
    $path = 'packages/backpack/store/css/name.css';

    if (!in_array($path, $styles, true)) {
        config()->set('backpack.base.styles', array_merge($styles, [$path]));
    }
  } 

  public function register()
  {
    $this->app->register(EventServiceProvider::class);
    $this->app->register(SettingsServiceProvider::class);

    $this->mergeConfigFrom(__DIR__ . '/config/modifications.php', 'bs.modifications');
    $this->mergeConfigFrom(__DIR__ . '/config/store.php', 'backpack.store');
    $this->mergeConfigFrom(__DIR__ . '/config/multistore.php', 'backpack.multistore');
    $this->mergeConfigFrom(__DIR__ . '/config/product_quality.php', 'backpack.pq');
  }

  protected function registerFacadeAlias()
  {
      // Делаем alias глобально
      AliasLoader::getInstance()->alias('Store', \Backpack\Store\Facades\Store::class);
  }
}
