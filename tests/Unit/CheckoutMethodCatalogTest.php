<?php

namespace Backpack\Store\Tests\Unit;

require_once dirname(__DIR__) . '/TestCase.php';

use Backpack\Store\app\Support\CheckoutMethodCatalog;
use Backpack\Store\Tests\TestCase;

class CheckoutMethodCatalogTest extends TestCase
{
    public function test_payment_methods_skip_disabled_online_provider(): void
    {
        config()->set('dress.payment.package_methods', [
            ['name' => 'liqpay', 'type' => 'online', 'label' => 'LiqPay'],
            ['name' => 'niftipay', 'type' => 'online', 'label' => 'Niftipay'],
            ['name' => 'bank', 'type' => 'transfer', 'label' => 'Bank'],
        ]);
        config()->set('dress.payment.methods', []);
        config()->set('dress.payment.provider_classes', [
            'liqpay' => 'FakeLiqpayProvider',
            'niftipay' => 'FakeNiftipayProvider',
        ]);
        config()->set('dress.payment.provider_settings.niftipay.enabled', false);

        $methods = CheckoutMethodCatalog::paymentMethods();

        $this->assertSame(['liqpay_online', 'bank_transfer'], array_values(array_map(
            fn (array $item) => $item['name'] . '_' . $item['type'],
            $methods
        )));
    }

    public function test_filter_payment_method_keys_removes_disabled_provider_keys(): void
    {
        config()->set('dress.payment.package_methods', [
            ['name' => 'liqpay', 'type' => 'online', 'label' => 'LiqPay'],
            ['name' => 'niftipay', 'type' => 'online', 'label' => 'Niftipay'],
            ['name' => 'bank', 'type' => 'transfer', 'label' => 'Bank'],
        ]);
        config()->set('dress.payment.methods', []);
        config()->set('dress.payment.provider_classes', [
            'liqpay' => 'FakeLiqpayProvider',
            'niftipay' => 'FakeNiftipayProvider',
        ]);
        config()->set('dress.payment.provider_settings.niftipay.enabled', false);

        $filtered = CheckoutMethodCatalog::filterPaymentMethodKeys([
            'liqpay_online',
            'niftipay_online',
            'bank_transfer',
        ]);

        $this->assertSame(['liqpay_online', 'bank_transfer'], $filtered);
    }
}
