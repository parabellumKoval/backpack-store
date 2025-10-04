<?php

namespace Backpack\Store\app\Providers;

use Illuminate\Support\ServiceProvider;
use Backpack\Store\app\Services\ProductLists\AvailabilityGate;
use Backpack\Store\app\Services\ProductLists\FilterEngine;
use Backpack\Store\app\Services\ProductLists\ListEngine;
use Backpack\Store\app\Services\ProductLists\ListSourceRegistry;
use Backpack\Store\app\Services\ProductLists\ProductHydrator;
use Backpack\Store\app\Services\ProductLists\SortingEngine;
use Backpack\Store\app\Services\ProductLists\Sources\{
    ManualListItemsResolver,
    LinksResolver,
    BoughtTogetherResolver,
    TagsResolver,
    CategoryResolver,
    BrandResolver,
    AttributesResolver,
    PriceBandResolver,
    BaseResolver
};

class ProductListsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListSourceRegistry::class, function () {
            $r = new ListSourceRegistry;
            // базовые источники
            $r->register('manual_list_items', ManualListItemsResolver::class);
            $r->register('links',             LinksResolver::class);
            $r->register('bought_together',   BoughtTogetherResolver::class);
            $r->register('tags',              TagsResolver::class);
            $r->register('category',          CategoryResolver::class);
            $r->register('brand',             BrandResolver::class);
            $r->register('attributes',        AttributesResolver::class);
            $r->register('price_band',        PriceBandResolver::class);
            $r->register('base',              BaseResolver::class);
            return $r;
        });

        $this->app->singleton(FilterEngine::class);
        $this->app->singleton(SortingEngine::class);
        $this->app->singleton(AvailabilityGate::class);
        $this->app->singleton(ProductHydrator::class);

        $this->app->singleton(ListEngine::class, function ($app) {
            return new ListEngine(
                $app->make(ListSourceRegistry::class),
                $app->make(FilterEngine::class),
                $app->make(SortingEngine::class),
                $app->make(AvailabilityGate::class),
                $app->make(ProductHydrator::class)
            );
        });
    }

    public function boot(): void
    {
        // Роуты API списков
        $this->loadRoutesFrom(__DIR__.'/../../routes/api/lists.php');
    }
}
