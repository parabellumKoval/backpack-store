<?php

use Illuminate\Support\Str;

if (!function_exists('store_stringify_value')) {
    /**
     * Safely stringify nested delivery/payment/user payloads for admin UI.
     *
     * @param  mixed  $value
     */
    function store_stringify_value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (!is_array($value)) {
            return '';
        }

        $parts = [];

        foreach ($value as $item) {
            $string = store_stringify_value($item);

            if ($string !== '') {
                $parts[] = $string;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }
}

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

if (!function_exists('store_payment_detail_label')) {
    function store_payment_detail_label(string $key): string
    {
        $map = [
            'account' => 'backpack-store::order.fields.payment.account',
            'card' => 'backpack-store::order.fields.payment.card',
            'invoice_number' => 'backpack-store::order.fields.payment.invoice_number',
            'comment' => 'backpack-store::order.fields.payment.comment',
        ];

        $translationKey = $map[$key] ?? null;

        if ($translationKey) {
            $translated = __($translationKey);
            if ($translated !== $translationKey) {
                return strip_tags($translated);
            }
        }

        return Str::title(str_replace(['_', '-'], ' ', $key));
    }
}

if (!function_exists('store_payment_lines')) {
    /**
     * Build a human-readable payment summary and hide provider metadata.
     *
     * @param  array|string|null  $payment
     * @return array<int, string>
     */
    function store_payment_lines($payment): array
    {
        if (is_string($payment)) {
            $line = store_payment_method_label($payment) ?? trim($payment);

            return $line !== '' ? [$line] : [];
        }

        if (!is_array($payment)) {
            return [];
        }

        $parts = array_filter($payment, static function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }

            return $value !== null && trim((string) $value) !== '';
        });

        if ($parts === []) {
            return [];
        }

        $extractString = static function (array $payload, array $keys) use (&$extractString): ?string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }

                $value = $payload[$key];

                if (is_scalar($value) || is_bool($value)) {
                    $string = store_stringify_value($value);
                    if ($string !== '') {
                        return $string;
                    }
                }

                if (is_array($value)) {
                    $nested = $extractString($value, $keys);
                    if ($nested !== null && $nested !== '') {
                        return $nested;
                    }
                }
            }

            foreach ($payload as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $nested = $extractString($value, $keys);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }

            return null;
        };

        $isNoise = static function (?string $value): bool {
            $value = trim((string) $value);

            if ($value === '') {
                return true;
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return true;
            }

            if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
                return true;
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                return true;
            }

            return in_array(Str::lower($value), [
                'new',
                'pending',
                'created',
                'processing',
                'success',
                'failed',
                'failure',
                'waiting',
                'paid',
                'paied',
                'unpaid',
            ], true);
        };

        $methodKey = $extractString($parts, ['method', 'paymentMethod', 'payment_method', 'methodKey', 'method_key', 'code', 'key']);
        $methodLabel = $methodKey ? store_payment_method_label($methodKey) : null;

        if (!$methodLabel) {
            $provider = $extractString($parts, ['provider']);
            if ($provider && !$isNoise($provider)) {
                $methodLabel = Str::title(str_replace(['_', '-'], ' ', $provider));
            }
        }

        $lines = [];

        if ($methodLabel) {
            $lines[] = $methodLabel;
        }

        $detailKeys = [
            'account' => ['account', 'iban'],
            'card' => ['card', 'masked_card', 'maskedCard', 'card_mask', 'cardMask'],
            'invoice_number' => ['invoice_number', 'invoiceNumber', 'invoice', 'invoice_no', 'invoiceNo'],
            'comment' => ['comment', 'note', 'instructions', 'details', 'description'],
        ];

        foreach ($detailKeys as $labelKey => $keys) {
            $value = $extractString($parts, $keys);

            if ($isNoise($value)) {
                continue;
            }

            $line = store_payment_detail_label($labelKey) . ': ' . trim((string) $value);

            if (!in_array($line, $lines, true)) {
                $lines[] = $line;
            }
        }

        if ($lines === []) {
            $fallback = $extractString($parts, ['title', 'label', 'name']);

            if (!$isNoise($fallback)) {
                $lines[] = trim((string) $fallback);
            }
        }

        return $lines;
    }
}

if (!function_exists('store_payment_summary')) {
    /**
     * @param  array|string|null  $payment
     */
    function store_payment_summary($payment): ?string
    {
        $lines = store_payment_lines($payment);

        return $lines !== [] ? implode(', ', $lines) : null;
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

if (!function_exists('store_delivery_lines')) {
    /**
     * Build human-readable delivery lines and hide technical provider refs.
     *
     * @param  array|string|null  $delivery
     * @return array<int, string>
     */
    function store_delivery_lines($delivery): array
    {
        if (is_string($delivery)) {
            $line = store_delivery_method_label($delivery) ?? trim($delivery);

            return $line !== '' ? [$line] : [];
        }

        if (!is_array($delivery)) {
            return [];
        }

        $technicalKeys = [
            'settlementRef',
            'warehouseRef',
            'streetRef',
            'price',
            'priceCurrency',
            'provider',
            'providerId',
            'provider_id',
            'cityRef',
            'regionRef',
            'areaRef',
            'pickupPointId',
            'branchId',
            'branch_id',
            'pointId',
            'point_id',
            'pickup_point_id',
            'addressId',
            'address_id',
            'carrierId',
            'carrier_id',
            'deliveryMethodId',
            'delivery_method_id',
        ];

        $knownKeys = [
            'method',
            'deliveryMethod',
            'delivery_method',
            'pickupPoint',
            'pickup_point',
            'type',
            'settlement',
            'city',
            'warehouse',
            'title',
            'region',
            'district',
            'area',
            'street',
            'address',
            'house',
            'room',
            'zip',
            'comment',
        ];

        $extractString = static function (array $payload, array $keys) use (&$extractString): ?string {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }

                $value = $payload[$key];

                if (is_scalar($value) || is_bool($value)) {
                    $string = store_stringify_value($value);
                    if ($string !== '') {
                        return $string;
                    }
                }

                if (is_array($value)) {
                    $nested = $extractString($value, $keys);
                    if ($nested !== null && $nested !== '') {
                        return $nested;
                    }
                }
            }

            foreach ($payload as $value) {
                if (!is_array($value)) {
                    continue;
                }

                $nested = $extractString($value, $keys);
                if ($nested !== null && $nested !== '') {
                    return $nested;
                }
            }

            return null;
        };

        $collectFallbackStrings = static function ($value, $key = null) use (&$collectFallbackStrings, $technicalKeys, $knownKeys): array {
            if (is_scalar($value) || is_bool($value)) {
                $string = store_stringify_value($value);
                return $string !== '' ? [$string] : [];
            }

            if (!is_array($value)) {
                return [];
            }

            $parts = [];

            foreach ($value as $nestedKey => $nestedValue) {
                if (is_string($nestedKey)) {
                    if (in_array($nestedKey, $technicalKeys, true) || in_array($nestedKey, $knownKeys, true)) {
                        continue;
                    }

                    if (preg_match('/(^|_)(id|ref)$/i', $nestedKey)) {
                        continue;
                    }
                }

                foreach ($collectFallbackStrings($nestedValue, $nestedKey) as $item) {
                    if (!in_array($item, $parts, true)) {
                        $parts[] = $item;
                    }
                }
            }

            return $parts;
        };

        $parts = array_filter($delivery, static function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }

            return $value !== null && trim((string) $value) !== '';
        });

        if ($parts === []) {
            return [];
        }

        $methodKey = $extractString($parts, ['method', 'deliveryMethod', 'delivery_method', 'methodKey', 'method_key', 'code', 'key', 'name', 'label']);
        $methodLabel = $methodKey ? store_delivery_method_label($methodKey) : null;

        $country = $extractString($parts, ['country', 'country_name', 'countryName']);
        $area = $extractString($parts, ['area', 'state', 'province']);
        $region = $extractString($parts, ['region', 'district', 'county']);
        $settlement = $extractString($parts, ['settlement', 'city', 'town', 'village']);
        $type = $extractString($parts, ['type', 'settlementType', 'settlement_type', 'cityType', 'city_type']);
        $warehouse = $extractString($parts, ['warehouse', 'pickupPoint', 'pickup_point', 'branch', 'point', 'title', 'name', 'label']);
        $street = $extractString($parts, ['street', 'address', 'addressLine', 'address_line']);
        $house = $extractString($parts, ['house', 'building']);
        $room = $extractString($parts, ['room', 'apartment', 'flat', 'office']);
        $zip = $extractString($parts, ['zip', 'postalCode', 'postal_code']);

        $locality = trim(implode(' ', array_filter([$type, $settlement])));
        $locationLine = trim(implode(', ', array_filter([$country, $area, $region, $locality])));

        $houseRoom = trim(implode('/', array_filter([$house, $room])));
        $addressLine = trim(implode(', ', array_filter([
            $street,
            $houseRoom,
            $zip,
        ])));

        $lines = array_values(array_filter([
            $methodLabel,
            $locationLine,
            $warehouse ?: null,
            !$warehouse ? $addressLine : null,
        ], static fn ($value) => is_string($value) && trim($value) !== ''));

        $fallbackParts = [];
        foreach ($parts as $key => $value) {
            if (in_array($key, $technicalKeys, true) || in_array($key, $knownKeys, true)) {
                continue;
            }

            foreach ($collectFallbackStrings($value, $key) as $normalized) {
                if ($normalized !== '' && !in_array($normalized, $fallbackParts, true)) {
                    $fallbackParts[] = $normalized;
                }
            }
        }

        foreach ($fallbackParts as $fallback) {
            if (!in_array($fallback, $lines, true)) {
                $lines[] = $fallback;
            }
        }

        return $lines;
    }
}

if (!function_exists('store_delivery_summary')) {
    /**
     * @param  array|string|null  $delivery
     */
    function store_delivery_summary($delivery): ?string
    {
        $lines = store_delivery_lines($delivery);

        return $lines !== [] ? implode(', ', $lines) : null;
    }
}
