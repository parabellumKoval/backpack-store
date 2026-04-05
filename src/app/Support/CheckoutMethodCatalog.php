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
        return static::mergeByMethodKey(
            config('dress.payment.methods', []),
            \Settings::get('dress.payment.methods', [])
        );
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
}
