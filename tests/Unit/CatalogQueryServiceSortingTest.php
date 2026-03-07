<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Services\Catalog\CatalogQueryService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class CatalogQueryServiceSortingTest extends TestCase
{
    public function test_manual_sort_default_order_uses_non_negative_then_negative_then_null(): void
    {
        $service = (new ReflectionClass(CatalogQueryService::class))->newInstanceWithoutConstructor();
        $compareMethod = new ReflectionMethod(CatalogQueryService::class, 'compareManualSortForDefault');
        $compareMethod->setAccessible(true);

        $compare = static function ($left, $right) use ($service, $compareMethod): int {
            return (int) $compareMethod->invoke($service, $left, $right);
        };

        $this->assertLessThan(0, $compare(100.0, -1.0));
        $this->assertGreaterThan(0, $compare(-1.0, 100.0));

        $this->assertLessThan(0, $compare(-1.0, null));
        $this->assertGreaterThan(0, $compare(null, -1.0));

        $this->assertLessThan(0, $compare(50.0, 10.0));
        $this->assertLessThan(0, $compare(-1.0, -10.0));

        $this->assertSame(0, $compare(null, null));
    }
}
