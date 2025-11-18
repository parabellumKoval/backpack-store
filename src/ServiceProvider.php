<?php

namespace Backpack\Store;

use Illuminate\Foundation\AliasLoader;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\File;

use Backpack\Store\app\Providers\EventServiceProvider;
use Backpack\Store\app\Providers\SearchServiceProvider;
use Backpack\Store\app\Providers\ProductListsServiceProvider;
use Backpack\Store\app\Providers\ShippingServiceProvider;

use Backpack\Store\app\Contracts\ProductService;
use Backpack\Store\app\Contracts\BonusService;
use Backpack\Store\app\Contracts\Admin\SupplierFormStrategy;
use Backpack\Store\app\Services\Product\ProductOrdersAttachService;
use Backpack\Store\app\Services\Product\ProductOrdersReportService;


class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
  public function boot()
  {
    // Добавляем кастомный путь для представлений Backpack
    View::addNamespace('store-crud', [
        resource_path('views/vendor/backpack/crud'),
        __DIR__.'/resources/views/crud',
    ]);

    // Добавляем кастомный путь для представлений Backpack
    View::addNamespace('crud', [
        resource_path('views/vendor/backpack/crud'),
        __DIR__.'/resources/views/vendor/backpack/crud',
    ]);

    View::addNamespace('backpack-store', [
        resource_path('views/vendor/backpack/store'),
        __DIR__.'/resources/views',
    ]);

    // Load translations
    $this->loadTranslationsFrom(__DIR__.'/resources/lang', 'backpack-store');

    // Migrations
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

    // Routes
    $this->addRoutes();
    
    $this->addPublishes();

    // Commands
    $this->commands([
      \Backpack\Store\app\Console\Commands\XmlSource::class,
      \Backpack\Store\app\Console\Commands\AttributesTransform::class,
      \Backpack\Store\app\Console\Commands\XmlCorrectInStock::class,
      \Backpack\Store\app\Console\Commands\ImportGoogleTaxonomy::class,
      \Backpack\Store\app\Console\Commands\CatalogCache::class,
      \Backpack\Store\app\Console\Commands\ProductPreprocessing::class,
      \Backpack\Store\app\Console\Commands\RefreshExchangeRates::class,
      \Backpack\Store\app\Console\Commands\SearchReindex::class,
      \Backpack\Store\app\Console\Commands\SearchApplySettings::class,
      \Backpack\Store\app\Console\Commands\RebuildCatalogCache::class
    ]);

    // Add CSS for Backpack
    $this->addStyles();

    // Facades
    $this->registerFacadeAlias();

    // Resolve dependencies for multistore (regions and currency) and single mode 
    $this->resolveMode();
    $this->resolveConverter();
  }


  public function register()
  {
    $this->app->register(ProductListsServiceProvider::class);
    $this->app->register(SearchServiceProvider::class);
    $this->app->register(EventServiceProvider::class);
    $this->app->register(ShippingServiceProvider::class);
    

    $this->mergeConfigFrom(
        __DIR__.'/config/backpack-settings-aliases.php',
        'backpack-settings.aliases_packages.parabellumkoval/store'
    );

    $this->mergeConfigFrom(__DIR__ . '/config/store.php', 'dress.store');
    $this->mergeConfigFrom(__DIR__ . '/config/search.php', 'dress.search');
    $this->mergeConfigFrom(__DIR__ . '/config/currency.php', 'dress.currency');
    $this->mergeConfigFrom(__DIR__ . '/config/modifications.php', 'dress.modifications');
    $this->mergeConfigFrom(__DIR__ . '/config/multistore.php', 'dress.multistore');
    $this->mergeConfigFrom(__DIR__ . '/config/product_quality.php', 'dress.pq');
    $this->mergeConfigFrom(__DIR__ . '/config/category.php', 'dress.category');
    $this->mergeConfigFrom(__DIR__ . '/config/brand.php', 'dress.brand');
    $this->mergeConfigFrom(__DIR__ . '/config/product_lists.php', 'dress.product_lists');
    $this->mergeConfigFrom(__DIR__ . '/config/source.php', 'dress.source');
    $this->mergeConfigFrom(__DIR__ . '/config/supplier.php', 'dress.supplier');
    $this->mergeConfigFrom(__DIR__ . '/config/product.php', 'dress.product');
    $this->mergeConfigFrom(__DIR__ . '/config/order.php', 'dress.order');
    $this->mergeConfigFrom(__DIR__ . '/config/promocode.php', 'dress.promocode');
    $this->mergeConfigFrom(__DIR__ . '/config/attribute.php', 'dress.attribute');
    $this->mergeConfigFrom(__DIR__ . '/config/upsell.php', 'dress.upsell');
    $this->mergeConfigFrom(__DIR__ . '/config/invoice.php', 'dress.invoice');
    $this->mergeConfigFrom(__DIR__ . '/config/delivery.php', 'dress.delivery');
    $this->mergeConfigFrom(__DIR__ . '/config/payment.php', 'dress.payment');

    $this->resolveBonusService();

    $this->app->singleton(ProductOrdersAttachService::class);
    $this->app->singleton(ProductOrdersReportService::class);
  }


  // PRIVATE
  private function resolveConverter() {
    $providerClass = config('dress.currency.provider');
    $this->app->bind(\Backpack\Store\app\Contracts\ExchangeRateProvider::class, $providerClass);
  }

  private function resolveBonusService(): void
  {
    $serviceClass = config('dress.order.bonus.service');

    if (!$serviceClass) {
        $serviceClass = \Backpack\Store\app\Services\Bonus\NullBonusService::class;
    }

    $this->app->bind(BonusService::class, $serviceClass);
  }

  private function resolveMode() {
    $isMultistore = \Store::isMulti();
    $isVertical = \Store::isModVertical();
    $isCacheTable = \Store::isCacheTable();

    $this->app->scoped(\Backpack\Store\app\Services\StoreContext::class, function ($app) {
        return $app->make(\Backpack\Store\app\Services\Resolvers\StoreContextResolver::class)->resolve();
    });

    // Upsale
    $this->app->singleton('store.upsell', fn() => new \Backpack\Store\app\Services\Product\UpsellService());

    // 
    $this->app->singleton(\Backpack\Store\app\Services\Resolvers\StoreContextResolver::class);

    $namespace = $isVertical? '\Backpack\Store\app\Services\Variant\Vertical': '\Backpack\Store\app\Services\Variant\Horizontal';
    $this->app->bind(\Backpack\Store\app\Contracts\VariantAvailability::class, "{$namespace}\VariantAvailability");
    $this->app->bind(\Backpack\Store\app\Contracts\Modification::class, "{$namespace}\Modification");

    $namespace = $isMultistore? '\Backpack\Store\app\Services\Region\Multi': '\Backpack\Store\app\Services\Region\Single';
    $this->app->bind(\Backpack\Store\app\Contracts\SupplierFilter::class, "{$namespace}\SupplierFilter");
    $this->app->bind(SupplierFormStrategy::class, "{$namespace}\Admin\SupplierFormStrategy");
    $this->app->bind(ProductService::class, "{$namespace}\ProductService");

    if($isCacheTable) {
      $this->app->bind(\Backpack\Store\app\Contracts\FilterService::class, \Backpack\Store\app\Services\Catalog\CatalogFilterService::class);
      $this->app->bind(\Backpack\Store\app\Contracts\QueryService::class, \Backpack\Store\app\Services\Catalog\CatalogQueryService::class);
    }else {
      $this->app->bind(\Backpack\Store\app\Contracts\FilterService::class, \Backpack\Store\app\Services\Catalog\ProductFilterService::class);
      $this->app->bind(\Backpack\Store\app\Contracts\QueryService::class, \Backpack\Store\app\Services\Catalog\ProductQueryService::class);
    }
  }

  private function addStyles() {
    $styles = config('backpack.base.styles', []);
    $path = 'packages/backpack/store/css/name.css';

    if (!in_array($path, $styles, true)) {
        config()->set('backpack.base.styles', array_merge($styles, [$path]));
    }
  } 

  private function addRoutes() {
    $this->loadRoutesFrom(__DIR__.'/routes/backpack/routes.php');

    $this->loadRoutesFrom(__DIR__.'/routes/api/catalog.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/product.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/category.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/order.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/cart.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/promocode.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/attribute.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/brand.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/search.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/lists.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/invoice.php');
    $this->loadRoutesFrom(__DIR__.'/routes/api/shipping.php');
  }

  private function addPublishes() {

    $this->publishes([
      __DIR__ . '/config/store.php' => config_path('/dress/store.php'),
      __DIR__ . '/config/search.php' => config_path('/dress/search.php'),
      __DIR__ . '/config/currency.php' => config_path('/dress/currency.php'),
      __DIR__ . '/config/modifications.php' => config_path('/dress/modifications.php'),
      __DIR__ . '/config/multistore.php' => config_path('/dress/multistore.php'),
      __DIR__ . '/config/product_quality.php' => config_path('/dress/product_quality.php'),
      __DIR__ . '/config/category.php' => config_path('/dress/category.php'),
      __DIR__ . '/config/brand.php' => config_path('/dress/brand.php'),
      __DIR__ . '/config/source.php' => config_path('/dress/source.php'),
      __DIR__ . '/config/supplier.php' => config_path('/dress/supplier.php'),
      __DIR__ . '/config/product.php' => config_path('/dress/product.php'),
      __DIR__ . '/config/order.php' => config_path('/dress/order.php'),
      __DIR__ . '/config/promocode.php' => config_path('/dress/promocode.php'),
      __DIR__ . '/config/attribute.php' => config_path('/dress/attribute.php'),
      __DIR__ . '/config/upsell.php' => config_path('/dress/upsell.php'),
      __DIR__ . '/config/invoice.php' => config_path('/dress/invoice.php'),
      __DIR__ . '/config/delivery.php' => config_path('/dress/delivery.php'),
      __DIR__ . '/config/payment.php' => config_path('/dress/payment.php'),
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
    $appPublicPath = public_path('packages/backpack/store');

    $this->publishes([
        $packagePublicPath => $appPublicPath,
    ], 'public');

    $this->publishes([
        __DIR__.'/app/Traits/Controllers/Admin' => base_path('app/Http/Controllers/Admin/Traits'),
      __DIR__.'/app/Traits/Models' => base_path('app/Http/Models/Traits')
    ], 'traits');
  }

  protected function registerFacadeAlias()
  {
      // Делаем alias глобально
      AliasLoader::getInstance()->alias('Store', \Backpack\Store\Facades\Store::class);
  }
}
