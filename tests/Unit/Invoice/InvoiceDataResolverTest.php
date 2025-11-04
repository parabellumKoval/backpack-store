<?php

namespace Backpack\Store\Tests\Unit;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Services\Invoice\InvoiceDataResolver;
use Backpack\Store\Tests\TestCase;

class InvoiceDataResolverTest extends TestCase
{
    public function test_buyer_address_uses_payment_fields_when_available(): void
    {
        config([
            'dress.invoice.bank_accounts' => [
                'UA' => [
                    'iban' => 'UA12345678901234567890123456',
                    'bic' => 'TESTUAUKXXX',
                ],
            ],
        ]);

        /** @var InvoiceDataResolver $resolver */
        $resolver = app(InvoiceDataResolver::class);

        $order = new Order();
        $order->setAttribute('id', 101);
        $order->setAttribute('code', 'INV-101');
        $order->setAttribute('currency_code', 'CZK');
        $order->setAttribute('country_code', 'UA');
        $order->setAttribute('info', [
            'user' => [
                'firstname' => 'Ivan',
                'lastname' => 'Ivanov',
                'email' => 'ivan@example.com',
            ],
            'products' => [
                [
                    'name' => 'Test product',
                    'price' => 100,
                    'amount' => 1,
                    'vat_rate' => 21,
                ],
            ],
            'payment' => [
                'method' => 'bank_transfer',
                'settlement' => 'Kyiv',
                'street' => 'Khreshchatyk',
                'house' => '10',
                'room' => '5',
                'zip' => '01001',
                'country' => 'UA',
                'firstname' => 'Petro',
                'lastname' => 'Petrenko',
                'phone' => '+380501234567',
            ],
        ]);

        $payload = $resolver->build($order, [
            'template' => 'cz_default',
            'locale' => 'cs_CZ',
            'number_pattern' => 'F{Y}{m}{order_id}',
            'due_days' => 14,
            'tax_offset_days' => 0,
        ]);

        $address = $payload['buyer']['address'];

        $this->assertSame('Khreshchatyk, 10/5', $address['street']);
        $this->assertSame('Kyiv', $address['city']);
        $this->assertSame('01001', $address['zip']);
        $this->assertSame('UA', $address['country']);
        $this->assertSame('Petro', $payload['buyer']['firstname']);
        $this->assertSame('Petrenko', $payload['buyer']['lastname']);
        $this->assertSame('+380501234567', $payload['buyer']['contacts']['phone']);
    }
}
