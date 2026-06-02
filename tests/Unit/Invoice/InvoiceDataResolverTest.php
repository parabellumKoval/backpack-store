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

    public function test_bank_transfer_invoice_includes_shipping_line_and_qr_amount(): void
    {
        /** @var InvoiceDataResolver $resolver */
        $resolver = app(InvoiceDataResolver::class);

        $order = new Order();
        $order->setAttribute('id', 102);
        $order->setAttribute('code', 'INV-102');
        $order->setAttribute('currency_code', 'CZK');
        $order->setAttribute('country_code', 'CZ');
        $order->setAttribute('shipping_total', 121.0);
        $order->setAttribute('info', [
            'products' => [
                [
                    'name' => 'Test product',
                    'price' => 100,
                    'amount' => 1,
                    'vat_rate' => 0,
                ],
            ],
            'payment' => [
                'method' => 'bank_transfer',
            ],
            'delivery' => [
                'method' => 'packeta_warehouse',
            ],
            'shippingQuote' => [
                'breakdown' => [
                    'net' => 100.0,
                    'vat' => 21.0,
                    'gross' => 121.0,
                    'vat_rate' => 21.0,
                ],
            ],
        ]);

        $payload = $resolver->build($order, [
            'template' => 'cz_default',
            'locale' => 'cs_CZ',
            'number_pattern' => 'F{Y}{m}{order_id}',
            'due_days' => 14,
            'tax_offset_days' => 0,
        ]);

        $expectedDeliveryLabel = store_delivery_method_label('packeta_warehouse');

        $this->assertCount(2, $payload['lines']);
        $this->assertSame(sprintf('Doprava (%s)', $expectedDeliveryLabel), $payload['lines'][1]['name']);
        $this->assertSame(100.0, $payload['lines'][1]['unit_price']);
        $this->assertSame(21.0, $payload['lines'][1]['total_vat']);
        $this->assertSame(121.0, $payload['lines'][1]['total_inc_vat']);
        $this->assertSame(221.0, $payload['totals']['grand_total']);
        $this->assertSame(221.0, $payload['qr_payload']['amount']);
    }

    public function test_non_bank_transfer_invoice_does_not_include_shipping_line(): void
    {
        /** @var InvoiceDataResolver $resolver */
        $resolver = app(InvoiceDataResolver::class);

        $order = new Order();
        $order->setAttribute('id', 103);
        $order->setAttribute('code', 'INV-103');
        $order->setAttribute('currency_code', 'CZK');
        $order->setAttribute('country_code', 'CZ');
        $order->setAttribute('shipping_total', 121.0);
        $order->setAttribute('info', [
            'products' => [
                [
                    'name' => 'Test product',
                    'price' => 100,
                    'amount' => 1,
                    'vat_rate' => 0,
                ],
            ],
            'payment' => [
                'method' => 'cash',
            ],
            'shippingQuote' => [
                'breakdown' => [
                    'net' => 100.0,
                    'vat' => 21.0,
                    'gross' => 121.0,
                    'vat_rate' => 21.0,
                ],
            ],
        ]);

        $payload = $resolver->build($order, [
            'template' => 'cz_default',
            'locale' => 'cs_CZ',
            'number_pattern' => 'F{Y}{m}{order_id}',
            'due_days' => 14,
            'tax_offset_days' => 0,
        ]);

        $this->assertCount(1, $payload['lines']);
        $this->assertSame(100.0, $payload['totals']['grand_total']);
        $this->assertSame(100.0, $payload['qr_payload']['amount']);
    }
}
