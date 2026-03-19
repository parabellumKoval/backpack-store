<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Services\Catalog\CatalogSyncTouch;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class CatalogSyncTouchTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    public function test_local_environment_with_async_queue_does_not_force_inline_sync(): void
    {
        $this->bootstrapApplication('local', [
            'queue.default' => 'database',
            'dress.store.catalog.touch_inline' => false,
        ]);

        $this->assertFalse($this->shouldSyncInline());
    }

    public function test_inline_override_forces_synchronous_sync(): void
    {
        $this->bootstrapApplication('local', [
            'queue.default' => 'database',
            'dress.store.catalog.touch_inline' => true,
        ]);

        $this->assertTrue($this->shouldSyncInline());
    }

    public function test_sync_queue_still_runs_inline(): void
    {
        $this->bootstrapApplication('production', [
            'queue.default' => 'sync',
            'dress.store.catalog.touch_inline' => false,
        ]);

        $this->assertTrue($this->shouldSyncInline());
    }

    protected function bootstrapApplication(string $environment, array $config): void
    {
        $app = new Application(__DIR__);
        $app->instance('config', new Repository($config));
        $app['env'] = $environment;

        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    protected function shouldSyncInline(bool $forceImmediate = false): bool
    {
        $method = new ReflectionMethod(CatalogSyncTouch::class, 'shouldSyncInline');
        $method->setAccessible(true);

        return $method->invoke(null, $forceImmediate);
    }
}
