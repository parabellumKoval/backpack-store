<?php

namespace Backpack\Store\app\Support;

class CheckoutMethodCatalog
{
    public static function deliveryMethods(): array
    {
        return static::mergeByMethodKey(
            config('dress.delivery.methods', []),
            \Settings::get('dress.delivery.methods', [])
        );
    }

    public static function paymentMethods(): array
    {
        return array_values(array_filter(
            static::mergeByMethodKey(
            config('dress.payment.package_methods', []),
            config('dress.payment.methods', []),
            \Settings::get('dress.payment.methods', [])
            ),
            fn (array $item) => static::isPaymentMethodAvailable($item)
        ));
    }

    public static function paymentMethodKeys(): array
    {
        return array_values(array_map(
            fn (array $item) => $item['name'] . '_' . $item['type'],
            static::paymentMethods()
        ));
    }

    public static function filterPaymentMethodKeys(mixed $value): array
    {
        $allowed = array_flip(static::paymentMethodKeys());

        return array_values(array_filter(
            static::normalizeMethodKeys($value),
            fn (string $key) => isset($allowed[$key])
        ));
    }

    protected static function mergeByMethodKey(mixed ...$sources): array
    {
        $merged = [];

        foreach ($sources as $source) {
            $items = static::normalizeList($source);
            foreach ($items as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                $type = trim((string) ($item['type'] ?? ''));

                if ($name === '' || $type === '') {
                    continue;
                }

                $merged[$name . '_' . $type] = $item;
            }
        }

        return array_values($merged);
    }

    protected static function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_is_list($value) ? $value : [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && array_is_list($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected static function normalizeMethodKeys(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn (mixed $item) => is_scalar($item) ? trim((string) $item) : null,
                $value
            )));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return static::normalizeMethodKeys($decoded);
            }
        }

        return [];
    }

    protected static function isPaymentMethodAvailable(array $item): bool
    {
        $name = trim((string) ($item['name'] ?? ''));
        $type = trim((string) ($item['type'] ?? ''));

        if ($name === '' || $type === '') {
            return false;
        }

        if ($type !== 'online') {
            return true;
        }

        if (!static::hasProvider($name)) {
            return true;
        }

        return static::isProviderEnabled($name);
    }

    protected static function hasProvider(string $provider): bool
    {
        return config("dress.payment.provider_classes.{$provider}") !== null
            || config("dress.payment.custom_provider_classes.{$provider}") !== null;
    }

    protected static function isProviderEnabled(string $provider): bool
    {
        $enabled = data_get(config("dress.payment.provider_settings.{$provider}", []), 'enabled');
        $customEnabled = data_get(config("dress.payment.custom_provider_settings.{$provider}", []), 'enabled');

        if ($customEnabled !== null) {
            $enabled = $customEnabled;
        }

        return static::toBoolean($enabled, true);
    }

    protected static function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $default;
    }
}
