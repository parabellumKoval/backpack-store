<?php

namespace Backpack\Store\Tests\Unit;

require_once dirname(__DIR__) . '/TestCase.php';

use Backpack\Settings\Services\Registry\Registry;
use Backpack\Store\app\Settings\DeliverySettingsRegistrar;
use Backpack\Store\Tests\TestCase;

class DeliverySettingsRegistrarTest extends TestCase
{
    public function test_shipping_methods_field_hides_messenger_express_toggle_option(): void
    {
        $registry = new Registry();

        (new DeliverySettingsRegistrar())->register($registry);

        $group = $registry->get('delivery');

        $this->assertNotNull($group);

        $field = null;

        foreach ($group->pages as $page) {
            foreach ($page->fields as $candidate) {
                if ($candidate->key === 'shipping.methods') {
                    $field = $candidate;
                    break 2;
                }
            }
        }

        $this->assertNotNull($field);

        $options = $field->toBackpackArray()['options'] ?? [];

        $this->assertArrayHasKey('messenger_address', $options);
        $this->assertArrayNotHasKey('messenger_express', $options);
    }

    public function test_messenger_cod_card_fee_percent_field_allows_decimal_step(): void
    {
        $registry = new Registry();

        (new DeliverySettingsRegistrar())->register($registry);

        $group = $registry->get('delivery');

        $this->assertNotNull($group);

        $field = null;

        foreach ($group->pages as $page) {
            foreach ($page->fields as $candidate) {
                if ($candidate->key === 'shipping.messenger.cod.card_fee_percent') {
                    $field = $candidate;
                    break 2;
                }
            }
        }

        $this->assertNotNull($field);
        $this->assertSame('0.01', $field->toBackpackArray()['attributes']['step'] ?? null);
    }
}
