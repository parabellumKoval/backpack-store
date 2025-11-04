<?php

namespace Backpack\Store\app\Services\Invoice;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\OrderInvoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class InvoiceStorageService
{
    public function persist(Order $order, array $payload, string $binary): OrderInvoice
    {
        $disk = $this->config('storage.disk', 'public');
        $path = $this->resolvePath($order, $payload['invoice_number']);
        $storage = Storage::disk($disk);

        $storage->put($path, $binary);

        $invoice = $order->invoices()->updateOrCreate(
            [
                'template' => $payload['template'],
                'payload_hash' => $payload['hash'],
            ],
            [
                'locale' => $payload['locale'],
                'currency' => $payload['currency'],
                'path' => $path,
                'filesize' => strlen($binary),
                'generated_at' => Carbon::now(),
                'meta' => Arr::only($payload, [
                    'invoice_number',
                    'order_number',
                    'issued_at',
                    'due_at',
                    'taxed_at',
                    'totals',
                    'seller',
                    'buyer',
                ]),
            ]
        );

        return $invoice;
    }

    public function findMatch(Order $order, string $template, string $hash): ?OrderInvoice
    {
        return $order->invoices()
            ->where('template', $template)
            ->where('payload_hash', $hash)
            ->latest('generated_at')
            ->first();
    }

    public function storeQr(OrderInvoice $invoice, array $qr): void
    {
        $invoice->update([
            'qr_format' => $qr['format'],
            'qr_path' => $qr['path'],
            'qr_payload_hash' => $qr['hash'],
            'qr_generated_at' => Carbon::now(),
        ]);
    }

    protected function resolvePath(Order $order, string $invoiceNumber): string
    {
        $mask = $this->config('storage.path_mask', 'invoices/{Y}/{m}/{invoice_number}.pdf');
        $time = Carbon::now();

        return strtr($mask, [
            '{Y}' => $time->format('Y'),
            '{m}' => $time->format('m'),
            '{d}' => $time->format('d'),
            '{order_id}' => $order->getKey(),
            '{invoice_number}' => $invoiceNumber,
        ]);
    }

    protected function config(string $key, $default = null)
    {
        return Config::get("dress.invoice.$key", $default);
    }
}
