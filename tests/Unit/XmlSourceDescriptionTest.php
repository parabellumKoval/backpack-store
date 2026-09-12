<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Console\Commands\XmlSource;
use PHPUnit\Framework\TestCase;

class XmlSourceDescriptionTest extends TestCase
{
    private function makeCommand(array $settings = [], array $xmlAttributeFields = []): XmlSource
    {
        $reflection = new \ReflectionClass(XmlSource::class);
        /** @var XmlSource $command */
        $command = $reflection->newInstanceWithoutConstructor();

        $this->setPrivate($command, 'settings', $settings);
        $this->setPrivate($command, 'xmlAttributeFields', $xmlAttributeFields);

        return $command;
    }

    private function setPrivate(object $object, string $property, $value): void
    {
        $reflection = new \ReflectionObject($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    private function invokePrivate(object $object, string $methodName, array $arguments = [])
    {
        $method = new \ReflectionMethod($object, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }

    /** @test */
    public function it_extracts_a_plain_description_tag()
    {
        $xml = simplexml_load_string('<offer><name>Test</name><description>Плоское описание</description></offer>');
        $command = $this->makeCommand(['fieldDescription' => 'description']);

        $this->assertSame(
            'Плоское описание',
            $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldDescription', null])
        );
    }

    /** @test */
    public function it_extracts_a_cdata_html_description()
    {
        $xml = simplexml_load_string('<offer><description><![CDATA[<p>Rich <b>HTML</b></p>]]></description></offer>');
        $command = $this->makeCommand(['fieldDescription' => 'description']);

        $this->assertSame(
            '<p>Rich <b>HTML</b></p>',
            $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldDescription', null])
        );
    }

    /** @test */
    public function it_extracts_description_from_a_param_by_name_condition()
    {
        $xml = simplexml_load_string('<offer><param name="Артикул">A1</param><param name="Описание">Из параметра</param></offer>');
        $command = $this->makeCommand(['fieldDescription' => 'param[name=Описание]']);

        $this->assertSame(
            'Из параметра',
            $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldDescription', null])
        );
    }

    /** @test */
    public function it_extracts_description_from_an_offer_attribute()
    {
        $xml = simplexml_load_string('<offer description="Из атрибута"><name>Test</name></offer>');
        $command = $this->makeCommand(['fieldDescription' => 'description'], ['fieldDescription']);

        $this->assertSame(
            'Из атрибута',
            $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldDescription', null])
        );
    }

    /** @test */
    public function it_returns_the_default_when_field_description_is_not_configured()
    {
        $xml = simplexml_load_string('<offer><description>Ignored</description></offer>');
        $command = $this->makeCommand([]);

        $this->assertNull(
            $this->invokePrivate($command, 'getItemFieldValue', [$xml, 'fieldDescription', null])
        );
    }

    /** @test */
    public function it_normalizes_blank_descriptions_to_null_and_trims()
    {
        $command = $this->makeCommand([]);

        $this->assertNull($this->invokePrivate($command, 'normalizeDescription', ['   ']));
        $this->assertNull($this->invokePrivate($command, 'normalizeDescription', [null]));
        $this->assertSame('Trimmed', $this->invokePrivate($command, 'normalizeDescription', ['  Trimmed  ']));
    }

    /** @test */
    public function it_sets_supplier_description_only_when_the_field_is_configured()
    {
        // Field configured -> description is persisted (and trimmed).
        $command = $this->makeCommand(['fieldDescription' => 'description']);
        $this->setPrivate($command, 'currentSource', (object)['supplier_id' => 7]);
        $this->setPrivate($command, 'rules', []);
        $this->setPrivate($command, 'stockRules', []);
        $this->setPrivate($command, 'exchange_rate', 1);

        $sp = new \stdClass();
        $data = [
            'price' => 100,
            'inStock' => 1,
            'code' => 'A',
            'barcode' => 'B',
            'description' => '  Supplier text  ',
        ];
        $this->invokePrivate($command, 'setSupplierData', [&$sp, $data]);

        $this->assertSame('Supplier text', $sp->description);

        // Field NOT configured -> description must not be touched at all,
        // so an existing value cannot be wiped on re-import.
        $command2 = $this->makeCommand([]);
        $this->setPrivate($command2, 'currentSource', (object)['supplier_id' => 7]);
        $this->setPrivate($command2, 'rules', []);
        $this->setPrivate($command2, 'stockRules', []);
        $this->setPrivate($command2, 'exchange_rate', 1);

        $sp2 = new \stdClass();
        $data2 = [
            'price' => 100,
            'inStock' => 1,
            'code' => 'A',
            'barcode' => 'B',
            'description' => 'should be ignored',
        ];
        $this->invokePrivate($command2, 'setSupplierData', [&$sp2, $data2]);

        $this->assertFalse(property_exists($sp2, 'description'));
    }
}
