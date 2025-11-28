<?php

use Illuminate\Support\Str;

if (!function_exists('store_currency_label')) {
    function store_currency_label(?string $code): string
    {
        if (!$code) {
            return '';
        }

        if (function_exists('currency_label')) {
            return currency_label($code);
        }

        $resolverClass = config('dress.store.currency_label_resolver');

        if (is_string($resolverClass) && $resolverClass !== '') {
            if (class_exists($resolverClass) && app()->bound($resolverClass)) {
                $resolver = app($resolverClass);
                if (method_exists($resolver, 'label')) {
                    return (string) $resolver->label($code);
                }
            }
        }

        return strtoupper($code);
    }
}

if (!function_exists('store_payment_method_label')) {
    function store_payment_method_label(?string $methodKey): ?string
    {
        if (!$methodKey) {
            return null;
        }

        $config = (array) config('dress.admin_orders.payments.' . $methodKey, []);
        $labelKey = $config['label'] ?? null;

        if ($labelKey) {
            $translated = __($labelKey);
            if ($translated !== $labelKey) {
                return strip_tags($translated);
            }
        }

        $defaultKey = 'backpack-store::shop.payment_methods.' . $methodKey;
        $translated = __($defaultKey);

        if ($translated !== $defaultKey) {
            return strip_tags($translated);
        }

        return Str::title(str_replace(['_', '-'], ' ', $methodKey));
    }
}

if (!function_exists('store_delivery_method_label')) {
    function store_delivery_method_label(?string $methodKey): ?string
    {
        if (!$methodKey) {
            return null;
        }

        $config = (array) config('dress.admin_orders.deliveries.' . $methodKey, []);
        $labelKey = $config['label'] ?? null;

        if ($labelKey) {
            $translated = __($labelKey);
            if ($translated !== $labelKey) {
                return strip_tags($translated);
            }
        }

        $defaultKey = 'backpack-store::shop.delivery_methods.' . $methodKey;
        $translated = __($defaultKey);

        if ($translated !== $defaultKey) {
            return strip_tags($translated);
        }

        return Str::title(str_replace(['_', '-'], ' ', $methodKey));
    }
}
