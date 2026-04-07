<?php

namespace Backpack\Store\app\Services\Payments\Providers;

use Backpack\Store\app\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

abstract class AbstractPaymentProvider
{
    protected function setting(string $key, mixed $default = null, ?string $storefront = null): mixed
    {
        $value = $this->configuredValue($this->providerSettings(), $key, $default);

        if ($storefront) {
            $value = $this->configuredValue(
                $this->providerSettings(),
                "storefronts.{$storefront}.{$key}",
                $value
            );
        }

        $value = $this->configuredValue($this->customProviderSettings(), $key, $value);

        if ($storefront) {
            $value = $this->configuredValue(
                $this->customProviderSettings(),
                "storefronts.{$storefront}.{$key}",
                $value
            );
        }

        return $value;
    }

    protected function providerSettings(): array
    {
        return (array) config("dress.payment.provider_settings.{$this->key()}", []);
    }

    protected function customProviderSettings(): array
    {
        return (array) config("dress.payment.custom_provider_settings.{$this->key()}", []);
    }

    protected function storefrontSettings(): array
    {
        return array_replace_recursive(
            (array) data_get($this->providerSettings(), 'storefronts', []),
            (array) data_get($this->customProviderSettings(), 'storefronts', [])
        );
    }

    protected function configuredValue(array $settings, string $key, mixed $default = null): mixed
    {
        $value = data_get($settings, $key);

        return $this->isConfigured($value) ? $value : $default;
    }

    protected function isConfigured(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    protected function resolveClientUrl(?Order $order = null, ?string $storefront = null): array
    {
        $storefront = $storefront ?: $this->storefrontFromOrder($order);
        $clientUrl = $this->setting('client_url', null, $storefront)
            ?: config('app.client_url')
            ?: config('app.url');
        $clientUrl = is_string($clientUrl) ? trim($clientUrl) : '';

        if ($clientUrl === '') {
            return ['/', false];
        }

        $clientUrl = rtrim($clientUrl, '/');

        if ($clientUrl === '') {
            return ['/', false];
        }

        return [$clientUrl, true];
    }

    protected function buildRedirectUrl(string $clientUrl, ?string $path = null): string
    {
        if (!$path) {
            return $clientUrl !== '' ? $clientUrl : '/';
        }

        $path = ltrim($path, '/');

        if ($clientUrl === '/' || $clientUrl === '') {
            return "/{$path}";
        }

        return "{$clientUrl}/{$path}";
    }

    protected function completeRedirectForOrder(?string $orderCode): string
    {
        $order = $this->findOrderByCode($orderCode);
        [$clientUrl] = $this->resolveClientUrl($order);

        return $this->buildRedirectUrl(
            $clientUrl,
            $orderCode ? "checkout/complete/{$orderCode}" : null
        );
    }

    protected function storefrontFromOrder(?Order $order): ?string
    {
        return $this->scalarString($order?->storefront_code)
            ?: $this->scalarString(data_get($order?->info, 'storefront'));
    }

    protected function findOrderByCode(?string $code): ?Order
    {
        if (!$code) {
            return null;
        }

        return Order::where('code', $code)->first();
    }

    protected function updateOrderPaymentInfo(Order $order, string $provider, array $data): void
    {
        $info = $order->info ?? [];
        $payment = (array) ($info['payment'] ?? []);
        $payment['provider'] = $provider;
        $payment[$provider] = array_merge((array) ($payment[$provider] ?? []), $data);
        $info['payment'] = $payment;
        $order->info = $info;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        Log::log($level, '[' . $this->key() . '] ' . $message, $context);
    }

    protected function scalarString(mixed $value): ?string
    {
        if (is_scalar($value) && $value !== '') {
            return (string) $value;
        }

        return null;
    }

    public function handleResult(Request $request)
    {
        return response()->noContent();
    }
}
