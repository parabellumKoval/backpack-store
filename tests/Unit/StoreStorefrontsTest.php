<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Services\Store;
use Backpack\Store\Tests\TestCase;

class StoreStorefrontsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('dress.storefront.enabled', true);
        config()->set('dress.storefront.default', 'main');
        config()->set('dress.storefront.values', [
            'main' => 'Основной storefront',
            'telegram' => [
                'label' => 'Telegram Mini App storefront',
                'badge' => [
                    'background' => '#dbEafe',
                ],
            ],
        ]);

        $ref = new \ReflectionClass(Store::class);
        $cache = $ref->getProperty('normalizedStorefrontsCache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
    }

    public function test_storefronts_support_string_and_badge_config(): void
    {
        $storefronts = Store::storefronts();

        $this->assertSame('Основной storefront', $storefronts['main']['label']);
        $this->assertSame('#E5E7EB', $storefronts['main']['badge']['background']);
        $this->assertSame('#111827', $storefronts['main']['badge']['color']);

        $this->assertSame('Telegram Mini App storefront', $storefronts['telegram']['label']);
        $this->assertSame('#DBEAFE', $storefronts['telegram']['badge']['background']);
        $this->assertSame('#111827', $storefronts['telegram']['badge']['color']);
    }
}
