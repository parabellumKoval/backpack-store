<?php

namespace Backpack\Store\app\Services\Invoice;

use BaconQrCode\Renderer\Image\Png;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class QrCodeGenerator
{
    public function generate(array $payload, string $format = null): array
    {
        if (empty($payload['iban'])) {
            throw new \InvalidArgumentException('Invoice QR payload requires IBAN.');
        }

        $payloadString = $this->buildPayloadString($payload);
        $format = $format ?: $this->config('qr.default_format', 'svg');

        $hash = hash('sha256', implode('|', [$payloadString, $format, $this->config('qr.size'), $this->config('qr.error_correction')]));
        $path = $this->pathFor($hash, $format, $payload['order_id'] ?? null);

        $disk = $this->config('qr.cache_disk', $this->config('storage.disk', 'public'));
        $storage = Storage::disk($disk);

        if (!$storage->exists($path)) {
            $binary = $this->renderQr($payloadString, $format);
            $storage->put($path, $binary);
        }

        return [
            'payload' => $payloadString,
            'format' => $format,
            'disk' => $disk,
            'path' => $path,
            'hash' => $hash,
            'binary' => $storage->get($path),
            'mime' => $format === 'svg' ? 'image/svg+xml' : 'image/png',
        ];
    }

    protected function buildPayloadString(array $payload): string
    {
        $parts = [
            'SPD*1.0',
            'ACC:' . $payload['iban'],
            'AM:' . number_format((float) $payload['amount'], 2, '.', ''),
            'CC:' . strtoupper($payload['currency']),
            'X-VS:' . $payload['variable_symbol'],
        ];

        if (!empty($payload['message'])) {
            $message = str_replace('*', ' ', (string) $payload['message']);
            $parts[] = 'MSG:' . $message;
        }

        return implode('*', $parts);
    }

    protected function renderQr(string $payload, string $format): string
    {
        $size = (int) $this->config('qr.size', 320);
        $rendererStyle = new RendererStyle($size);

        if ($format === 'svg') {
            $renderer = new ImageRenderer($rendererStyle, new SvgImageBackEnd());
        } else {
            $renderer = new ImageRenderer($rendererStyle, new Png());
        }

        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }

    protected function pathFor(string $hash, string $format, ?int $orderId = null): string
    {
        $mask = $this->config('qr.cache_path', 'invoices/qr/{order_id}-{hash}.{format}');

        return strtr($mask, [
            '{hash}' => $hash,
            '{format}' => $format,
            '{order_id}' => (string) ($orderId ?? '0'),
        ]);
    }

    protected function config(string $key, $default = null)
    {
        return Config::get("dress.invoice.$key", $default);
    }
}
