<?php

namespace Backpack\Store\Tests\Feature;

use Backpack\Store\app\Events\CategoryChanged;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Observers\CategoryObserver;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class CategoryObserverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_saved_dispatches_event_but_skips_catalog_touch_for_content_only_changes(): void
    {
        $container = new Container();
        $dispatcher = new Dispatcher($container);
        $container->instance('events', $dispatcher);
        $container->instance(\Illuminate\Contracts\Events\Dispatcher::class, $dispatcher);
        $container->instance(\Backpack\Store\app\Services\Store::class, new class {
            public function isCacheTable(): bool
            {
                return true;
            }
        });

        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $events = [];
        $dispatcher->listen(CategoryChanged::class, function (CategoryChanged $event) use (&$events): void {
            $events[] = $event;
        });

        $category = Mockery::mock(Category::class);
        $category->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $category->shouldReceive('getAttribute')->with('slug')->andReturn('osnovnoe');
        $category->shouldReceive('wasChanged')->andReturn(false);

        $observer = new class extends CategoryObserver {
            public int $touchCalls = 0;

            protected function touchCatalogProducts(Category $category): void
            {
                $this->touchCalls++;
            }
        };

        $observer->saved($category);

        $this->assertCount(1, $events);
        $this->assertSame('saved', $events[0]->action);
        $this->assertSame(2, $events[0]->categoryId);
        $this->assertSame('osnovnoe', $events[0]->slug);
        $this->assertSame(0, $observer->touchCalls);
    }

    public function test_saved_touches_catalog_when_category_affects_cached_catalog_shape(): void
    {
        $container = new Container();
        $dispatcher = new Dispatcher($container);
        $container->instance('events', $dispatcher);
        $container->instance(\Illuminate\Contracts\Events\Dispatcher::class, $dispatcher);
        $container->instance(\Backpack\Store\app\Services\Store::class, new class {
            public function isCacheTable(): bool
            {
                return true;
            }
        });

        Container::setInstance($container);
        Facade::setFacadeApplication($container);

        $observer = new class extends CategoryObserver {
            public int $touchCalls = 0;

            protected function touchCatalogProducts(Category $category): void
            {
                $this->touchCalls++;
            }
        };

        foreach (['countries', 'store_only_countries', 'parent_id'] as $field) {
            $category = Mockery::mock(Category::class);
            $category->shouldReceive('getAttribute')->with('id')->andReturn(2);
            $category->shouldReceive('getAttribute')->with('slug')->andReturn('osnovnoe');
            $category->shouldReceive('wasChanged')
                ->andReturnUsing(fn (string $candidate): bool => $candidate === $field);
            $observer->saved($category);
        }

        $this->assertSame(3, $observer->touchCalls);
    }
}
