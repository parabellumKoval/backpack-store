<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Console\Commands\XmlSource;
use PHPUnit\Framework\TestCase;

class XmlSourceFieldResolutionTest extends TestCase
{
    private function makeCommand(array $settings = [], array $xmlAttributeFields = []): XmlSource
    {
        $reflection = new \ReflectionClass(XmlSource::class);
        /** @var XmlSource $command */
        $command = $reflection->newInstanceWithoutConstructor();

        $settingsProperty = $reflection->getProperty('settings');
        $settingsProperty->setAccessible(true);
        $settingsProperty->setValue($command, $settings);

        $xmlAttributeFieldsProperty = $reflection->getProperty('xmlAttributeFields');
        $xmlAttributeFieldsProperty->setAccessible(true);
        $xmlAttributeFieldsProperty->setValue($command, $xmlAttributeFields);

        return $command;
    }

    private function invokePrivate(object $object, string $methodName, array $arguments = [])
    {
        $method = new \ReflectionMethod($object, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    /** @test */
    public function it_reads_field_values_from_offer_attributes_via_fields_in_attributes_setting()
    {
        $xml = simplexml_load_string('<offer in_stock="true" available="true"><name>Test</name><categoryId>478</categoryId></offer>');
        $command = $this->makeCommand([
            'fieldInStock' => 'in_stock',
            'fieldCategory' => 'categoryId',
        ], ['fieldInStock']);

        $this->assertSame('true', $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldInStock', null]));
        $this->assertSame('478', $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldCategory', null]));
    }

    /** @test */
    public function it_reads_field_values_from_offer_attributes_via_explicit_attribute_path()
    {
        $xml = simplexml_load_string('<offer in_stock="false"><name>Test</name></offer>');
        $command = $this->makeCommand([
            'fieldInStock' => '@in_stock',
        ]);

        $this->assertSame('false', $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldInStock', null]));
    }
}
