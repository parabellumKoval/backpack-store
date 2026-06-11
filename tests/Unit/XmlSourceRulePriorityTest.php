<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Console\Commands\XmlSource;
use PHPUnit\Framework\TestCase;

class XmlSourceRulePriorityTest extends TestCase
{
    private function makeCommand(array $rules): XmlSource
    {
        $reflection = new \ReflectionClass(XmlSource::class);
        /** @var XmlSource $command */
        $command = $reflection->newInstanceWithoutConstructor();

        $rulesProperty = $reflection->getProperty('rules');
        $rulesProperty->setAccessible(true);
        $rulesProperty->setValue($command, $rules);

        return $command;
    }

    private function validateData(XmlSource $command, array $data): bool
    {
        $method = new \ReflectionMethod($command, 'validateData');
        $method->setAccessible(true);

        return $method->invoke($command, $data);
    }

    /** @test */
    public function blacklist_category_has_priority_over_whitelist_brand()
    {
        $command = $this->makeCommand([
            'whitelist' => [[
                'target' => 'brand',
                'brands' => ['BioTech'],
            ]],
            'blacklist' => [[
                'target' => 'category',
                'categories' => ['Уцінка'],
            ]],
        ]);

        $this->assertFalse($this->validateData($command, [
            'brand' => 'BioTech',
            'category' => '478',
            'category_name' => 'Уцінка',
            'name' => 'BioTech Test Product',
            'code' => 'SKU-1',
            'barcode' => null,
            'price' => 100,
            'inStock' => 'true',
        ]));
    }

    /** @test */
    public function whitelist_still_allows_product_when_blacklist_does_not_match()
    {
        $command = $this->makeCommand([
            'whitelist' => [[
                'target' => 'brand',
                'brands' => ['BioTech'],
            ]],
            'blacklist' => [[
                'target' => 'category',
                'categories' => ['Уцінка'],
            ]],
        ]);

        $this->assertTrue($this->validateData($command, [
            'brand' => 'BioTech',
            'category' => '2',
            'category_name' => 'BCAA',
            'name' => 'BioTech Test Product',
            'code' => 'SKU-2',
            'barcode' => null,
            'price' => 100,
            'inStock' => 'true',
        ]));
    }
}
