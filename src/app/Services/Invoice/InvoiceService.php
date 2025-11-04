<?php

namespace Backpack\Store\app\Services\Invoice;

use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\OrderInvoice;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class InvoiceService
{
    public function __construct(
        protected InvoiceDataResolver $dataResolver,
        protected InvoiceRenderer $renderer,
        protected QrCodeGenerator $qrCodeGenerator,
        protected InvoiceStorageService $storage
    ) {
    }

    public function generate(Order $order, array $options = []): array
    {
        [$payload, $templateConfig] = $this->buildPayload($order, $options);
        $force = (bool) Arr::get($options, 'force', false);

        $existing = $this->storage->findMatch($order, $payload['template'], $payload['hash']);

        if (!$force && $existing && $this->fileExists($existing->path)) {
            $payload['invoice'] = $existing;
            $payload['binary'] = Storage::disk($this->config('storage.disk', 'public'))->get($existing->path);
        } else {
            $html = view($templateConfig['view'], ['invoice' => $payload])->render();
            $binary = $this->renderer->render($html, $templateConfig);
            $payload['binary'] = $binary;

            $invoice = $this->storage->persist($order, $payload, $binary);
            $payload['invoice'] = $invoice;
        }

        $this->storage->storeQr($payload['invoice'], $payload['qr']);

        return $payload;
    }

    public function renderFresh(Order $order, array $options = []): array
    {
        [$payload, $templateConfig] = $this->buildPayload($order, $options);

        $html = view($templateConfig['view'], ['invoice' => $payload])->render();
        $binary = $this->renderer->render($html, $templateConfig);

        return [
            'payload' => $payload,
            'binary' => $binary,
            'filename' => $this->fileNameFromPayload($payload),
        ];
    }

    public function templateHtml(Order $order, array $options = []): array
    {
        [$payload, $templateConfig] = $this->buildPayload($order, $options);

        $html = view($templateConfig['view'], ['invoice' => $payload])->render();

        return [
            'html' => $html,
            'payload' => $payload,
        ];
    }

    public function stream(OrderInvoice $invoice): array
    {
        $disk = $this->config('storage.disk', 'public');
        $storage = Storage::disk($disk);

        if (!$storage->exists($invoice->path)) {
            throw new \RuntimeException('Invoice file not available.');
        }

        return [
            'disk' => $disk,
            'path' => $invoice->path,
            'stream' => $storage->get($invoice->path),
        ];
    }

    public function downloadResponse(OrderInvoice $invoice)
    {
        $disk = $this->config('storage.disk', 'public');
        return Storage::disk($disk)->download($invoice->path, $this->downloadFileName($invoice));
    }

    public function signedUrl(OrderInvoice $invoice, ?string $ttl = null): string
    {
        $routeName = $this->config('signed_url.route', 'backpack.store.invoices.download-signed');
        $ttl = $ttl ?: $this->config('signed_url.ttl', 'P7D');

        $expiration = now()->add($this->parseTtl($ttl));

        return URL::temporarySignedRoute(
            $routeName,
            $expiration,
            [
                'invoice' => $invoice->getKey(),
                'order' => $invoice->order_id,
            ]
        );
    }

    public function fileName(OrderInvoice $invoice): string
    {
        return $this->downloadFileName($invoice);
    }

    public function latestInvoice(Order $order): ?OrderInvoice
    {
        return $order->invoices()
            ->latest('generated_at')
            ->latest('id')
            ->first();
    }

    public function existingQr(OrderInvoice $invoice): ?array
    {
        if (!$invoice->qr_path) {
            return null;
        }

        $disk = $this->qrDisk();
        $storage = Storage::disk($disk);

        if (!$storage->exists($invoice->qr_path)) {
            return null;
        }

        $format = $invoice->qr_format ?: $this->config('qr.default_format', 'svg');
        $binary = $storage->get($invoice->qr_path);

        $qr = [
            'payload' => null,
            'format' => $format,
            'disk' => $disk,
            'path' => $invoice->qr_path,
            'hash' => $invoice->qr_payload_hash,
            'binary' => $binary,
            'mime' => $format === 'svg' ? 'image/svg+xml' : 'image/png',
        ];

        $qr['data_url'] = $this->makeDataUrl($qr);
        $qr['inline_svg'] = $format === 'svg' ? $this->stripXmlDeclaration($binary) : null;

        return $qr;
    }

    protected function downloadFileName(OrderInvoice $invoice): string
    {
        $number = Arr::get($invoice->meta, 'invoice_number') ?? $invoice->payload_hash;
        $safe = Str::of($number)->replaceMatches('/[^A-Za-z0-9\-]+/', '_')->trim('_')->lower();

        return sprintf('invoice-%s.pdf', $safe);
    }

    protected function calculatePayloadHash(array $payload): string
    {
        $hashBasis = Arr::only($payload, [
            'invoice_number',
            'order_number',
            'issued_at',
            'due_at',
            'taxed_at',
            'seller',
            'buyer',
            'lines',
            'totals',
            'currency',
            'qr_payload',
        ]);

        return hash('sha256', json_encode($hashBasis, JSON_THROW_ON_ERROR));
    }

    protected function fileExists(string $path): bool
    {
        return Storage::disk($this->config('storage.disk', 'public'))->exists($path);
    }

    protected function parseTtl(string $ttl): \DateInterval
    {
        if (preg_match('/^\d+$/', $ttl)) {
            return new \DateInterval('PT' . (int) $ttl . 'S');
        }

        return new \DateInterval($ttl);
    }

    protected function makeDataUrl(array $qr): string
    {
        $data = base64_encode($qr['binary']);
        $mime = $qr['format'] === 'svg' ? 'image/svg+xml' : 'image/png';

        return "data:{$mime};base64,{$data}";
    }

    protected function qrDisk(): string
    {
        return $this->config('qr.cache_disk', $this->config('storage.disk', 'public'));
    }

    protected function config(string $key, $default = null)
    {
        return \Settings::get("dress.invoice.$key", $default);
    }

    protected function fileNameFromPayload(array $payload): string
    {
        $number = Arr::get($payload, 'invoice_number') ?? Arr::get($payload, 'order_number') ?? Arr::get($payload, 'order.id');
        $safe = Str::of($number ?: 'preview')->replaceMatches('/[^A-Za-z0-9\-]+/', '_')->trim('_')->lower();

        return sprintf('invoice-%s.pdf', $safe);
    }

    /**
     * @return array{0: array, 1: array}
     */
    protected function buildPayload(Order $order, array $options = []): array
    {
        $templateKey = $options['template'] ?? $this->config('default_template');
        $templateConfig = $this->config("templates.$templateKey");

        if (!$templateConfig) {
            throw new \InvalidArgumentException("Invoice template [$templateKey] is not registered.");
        }

        $context = [
            'template' => $templateKey,
            'locale' => $options['locale'] ?? $this->config('locale'),
            'number_pattern' => $this->config('numbering.pattern'),
            'due_days' => (int) $this->config('numbering.due_days', 14),
            'tax_offset_days' => (int) $this->config('numbering.tax_date_offset', 0),
        ];

        $payload = $this->dataResolver->build($order, $context);
        $payload['template'] = $templateKey;
        $payload['locale'] = $context['locale'];
        $payload['hash'] = $this->calculatePayloadHash($payload);
        $payload['template_config'] = $templateConfig;

        $qrPayload = array_merge($payload['qr_payload'], [
            'order_id' => $order->getKey(),
        ]);

        $qr = $this->qrCodeGenerator->generate($qrPayload, $options['qr_format'] ?? null);
        $payload['qr'] = array_merge($qr, [
            'data_url' => $this->makeDataUrl($qr),
            'inline_svg' => $qr['format'] === 'svg' ? $this->stripXmlDeclaration($qr['binary']) : null,
        ]);

        return [$payload, $templateConfig];
    }

    protected function stripXmlDeclaration(string $svg): string
    {
        return trim(preg_replace('/<\?xml.*?\?>/i', '', $svg) ?? $svg);
    }
}
