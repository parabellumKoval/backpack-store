<?php

namespace Backpack\Store\Tests\Feature;

use Backpack\Store\app\Events\ProductListChanged;
use Backpack\Store\app\Models\ProductList;
use Backpack\Store\app\Observers\ProductListObserver;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;

class ProductListWebhookEventTest extends TestCase
{
    public function test_product_list_observer_dispatches_changed_events(): void
    {
        $container = new Container();
        $dispatcher = new Dispatcher($container);
        $container->instance('events', $dispatcher);
        $container->instance(\Illuminate\Contracts\Events\Dispatcher::class, $dispatcher);
        Container::setInstance($container);

        $events = [];
        $dispatcher->listen(ProductListChanged::class, function (ProductListChanged $event) use (&$events): void {
            $events[] = $event;
        });

        $list = new ProductList();
        $list->id = 777;
        $list->slug = 'bestsellers';

        $observer = new ProductListObserver();
        $observer->saved($list);
        $observer->deleted($list);

        $this->assertCount(2, $events);
        $this->assertSame('saved', $events[0]->action);
        $this->assertSame('deleted', $events[1]->action);
        $this->assertSame(777, $events[0]->listId);
        $this->assertSame('bestsellers', $events[0]->slug);
    }

    public function test_webhook_config_links_product_list_changes_to_frontend_refresh_unit(): void
    {
        $config = require __DIR__ . '/../../../webhooks/config/webhooks.php';

        $this->assertContains('homepage.lists.updated', $config['units']['refresh_homepage_lists']['events']);
        $this->assertSame(
            ProductListChanged::class,
            $config['events']['homepage.lists.updated']['sources'][0]['class']
        );
        $this->assertSame(
            '/api/_fetcher/homepage-main-lists/refresh',
            $config['units']['refresh_homepage_lists']['url']
        );
    }
}
