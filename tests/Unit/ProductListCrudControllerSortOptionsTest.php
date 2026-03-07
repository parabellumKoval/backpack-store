<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Http\Controllers\Admin\ProductListCrudController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ProductListCrudControllerSortOptionsTest extends TestCase
{
    public function test_price_is_single_criterion_without_built_in_direction(): void
    {
        $controller = (new ReflectionClass(ProductListCrudController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ProductListCrudController::class, 'sortOptions');
        $method->setAccessible(true);

        $options = $method->invoke($controller);

        $this->assertArrayHasKey('price', $options);
        $this->assertArrayNotHasKey('price_asc', $options);
        $this->assertArrayNotHasKey('price_desc', $options);
    }

    public function test_legacy_price_shortcuts_are_normalized_for_edit_form(): void
    {
        $controller = (new ReflectionClass(ProductListCrudController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(ProductListCrudController::class, 'sortAssocToRows');
        $method->setAccessible(true);

        $rows = $method->invoke($controller, ['price_desc', ['criterion' => 'price_asc', 'direction' => 'desc']]);

        $this->assertSame('price', $rows[0]['criterion']);
        $this->assertSame('desc', $rows[0]['direction']);
        $this->assertSame('price', $rows[1]['criterion']);
        $this->assertSame('desc', $rows[1]['direction']);
    }
}
