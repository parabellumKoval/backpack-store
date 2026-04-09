<?php

namespace Backpack\Store\app\Services;

use \Backpack\Store\app\Services\StoreContext;

class Store
{
    protected static ?array $normalizedStorefrontsCache = null;

    public static function context(): StoreContext
    {
        return app(StoreContext::class);
    }


    public static function withContext(string $country, string $currency, callable $callback, ?string $storefront = null)
    {
        $cls = StoreContext::class;
        $prev = app()->bound($cls) ? app($cls) : null;
        $resolvedStorefront = static::normalizeStorefrontCode($storefront)
            ?? $prev?->storefront
            ?? static::defaultStorefront();

        app()->instance($cls, new StoreContext($country, $currency, $resolvedStorefront));
        try {
            return $callback();
        } finally {
            if ($prev) app()->instance($cls, $prev);
            else app()->forgetInstance($cls);
        }
    }
    
    // Boolean
    public static function isMods(): bool
    {
        return \Settings::get('dress.modifications.enabled', true);
    }

    public static function modMode(): string
    {
        return \Settings::get('dress.modifications.mode', 'horizontal');
    }

    public static function isModHorizontall(): bool
    {
        return self::isMods() && self::modMode() === 'horizontal';
    }

    public static function isModVertical(): bool
    {
        return self::isMods() && self::modMode() === 'vertical';
    }

    public static function isMulti(): bool
    {
        return \Settings::get('dress.multistore.enabled', false);
    }

    public static function isGlobalRegionEnabled(): bool
    {
        return \Settings::get('dress.multistore.support_global', true);
    }

    public static function isCacheTable(): bool 
    {
        $configured = config('dress.store.catalog_table_cache', config('backpack.store.catalog_table_cache', false));

        return (bool) \Settings::get('dress.store.catalog_table_cache', $configured);
    }

    // Getters
    public static function globalRegionCode(): string|null 
    {
        return \Settings::get('dress.store.global_region_code', 'zz');
    }

    // 
    public static function globalRegion(): string|null 
    {
        return self::isGlobalRegionEnabled()? self::globalRegionCode(): null;
    }

    public static function country(): string
    {
        return request()->get('country')
            ?? session('country')
            ?? \Settings::get('dress.multistore.default_country');
    }

    public static function currency(): string
    {
        return request()->get('currency')
            ?? self::countryCurrency()
            ?? session('currency')
            ?? \Settings::get('dress.multistore.default_currency');
    }

    public static function storefront(): string
    {
        $requestKey = static::storefrontRequestKey();
        $headerName = static::storefrontHeaderName();

        $candidate = request()->get($requestKey)
            ?? request()->header($headerName)
            ?? session($requestKey)
            ?? (app()->bound(StoreContext::class) ? app(StoreContext::class)->storefront : null)
            ?? static::defaultStorefront();

        return static::normalizeStorefrontCode($candidate) ?? static::defaultStorefront();
    }

    public static function isStorefrontEnabled(): bool
    {
        return (bool) config('dress.storefront.enabled', false);
    }

    public static function storefrontRequestKey(): string
    {
        return (string) config('dress.storefront.request_key', 'storefront');
    }

    public static function storefrontHeaderName(): string
    {
        return (string) config('dress.storefront.header_name', 'X-Storefront');
    }

    public static function defaultStorefront(): string
    {
        $configured = static::normalizeStorefrontCode(config('dress.storefront.default', 'main'));

        if ($configured !== null) {
            return $configured;
        }

        $storefronts = static::storefronts();
        return array_key_first($storefronts) ?: 'main';
    }

    public static function storefronts(): array
    {
        if (static::$normalizedStorefrontsCache !== null) {
            return static::$normalizedStorefrontsCache;
        }

        $raw = (array) config('dress.storefront.values', []);
        $normalized = [];

        foreach ($raw as $key => $item) {
            if (is_string($item)) {
                $item = [
                    'label' => $item,
                ];
            } elseif (!is_array($item)) {
                $item = [];
            }

            $code = static::normalizeStorefrontCode($item['code'] ?? $key);
            if ($code === null) {
                continue;
            }

            if (($item['enabled'] ?? true) === false) {
                continue;
            }

            $normalized[$code] = array_merge($item, [
                'code' => $code,
                'label' => (string) ($item['label'] ?? ucfirst($code)),
                'badge' => static::normalizeStorefrontBadge($item['badge'] ?? null),
            ]);
        }

        $default = static::normalizeStorefrontCode(config('dress.storefront.default', 'main')) ?? 'main';
        if (!isset($normalized[$default])) {
            $normalized[$default] = [
                'enabled' => true,
                'code' => $default,
                'label' => ucfirst($default),
                'badge' => static::normalizeStorefrontBadge(null),
                'is_default' => true,
            ];
        }

        return static::$normalizedStorefrontsCache = $normalized;
    }

    public static function storefrontMeta(?string $code): array
    {
        $storefronts = static::storefronts();
        $normalizedCode = static::normalizeStorefrontCode($code);

        if ($normalizedCode !== null && isset($storefronts[$normalizedCode])) {
            return $storefronts[$normalizedCode];
        }

        if ($normalizedCode !== null) {
            return [
                'code' => $normalizedCode,
                'label' => ucfirst($normalizedCode),
                'badge' => static::normalizeStorefrontBadge(null),
            ];
        }

        $defaultCode = static::defaultStorefront();
        if (isset($storefronts[$defaultCode])) {
            return $storefronts[$defaultCode];
        }

        return [
            'code' => $defaultCode,
            'label' => ucfirst($defaultCode),
            'badge' => static::normalizeStorefrontBadge(null),
        ];
    }

    public static function storefrontOptions(): array
    {
        $values = static::storefronts();
        return array_column($values, 'label', 'code');
    }

    public static function storefrontSettingsOverrides(): array
    {
        return (array) config('dress.storefront.settings_overrides', []);
    }

    public static function applyUnassignedCategoriesToDefaultStorefront(): bool
    {
        return (bool) config('dress.storefront.apply_unassigned_to_default', false);
    }

    public static function normalizeStorefrontCode(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $code));
        $normalized = preg_replace('/[^a-z0-9_-]/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    protected static function normalizeStorefrontBadge($badge): array
    {
        if (is_string($badge)) {
            $badge = ['background' => $badge];
        }

        if (!is_array($badge)) {
            $badge = [];
        }

        $background = static::normalizeHexColor($badge['background'] ?? $badge['bg'] ?? null) ?? '#E5E7EB';
        $color = static::normalizeHexColor($badge['color'] ?? $badge['text'] ?? null)
            ?? static::contrastTextColor($background);

        return [
            'background' => $background,
            'color' => $color,
        ];
    }

    protected static function normalizeHexColor(?string $color): ?string
    {
        if (!is_string($color)) {
            return null;
        }

        $normalized = trim($color);
        if ($normalized === '') {
            return null;
        }

        if ($normalized[0] !== '#') {
            $normalized = '#'.$normalized;
        }

        if (!preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $normalized)) {
            return null;
        }

        if (strlen($normalized) === 4) {
            return sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $normalized[1],
                $normalized[2],
                $normalized[3]
            );
        }

        return strtoupper($normalized);
    }

    protected static function contrastTextColor(string $background): string
    {
        $hex = ltrim($background, '#');

        if (strlen($hex) !== 6) {
            return '#111827';
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance >= 160 ? '#111827' : '#FFFFFF';
    }

    // Получить все доступные страны
    public static function countries(): array
    {
        $countries = \Settings::get('dress.multistore.countries', []);
        $enabled_countries = array_filter($countries, function($item) {
            return !isset($item['enabled']) || $item['enabled'] !== false? true: false;
        });

        if(self::globalRegion()) {
            $enabled_countries[self::globalRegion()] = self::getGlobalRegionUnit();
        }

        return $enabled_countries;
    }

    public static function countryOptions(): array
    {
        $values = self::countries();
        $countries = array_column($values, 'country', 'code');

        return $countries;
    }

    public static function defaultCountryLocales(): array
    {
        $values = self::countries();
        $countries = array_column($values, 'locale', 'code');

        return $countries;
    }

    // Получить все доступные валюты (например, как массив кодов)
    public static function currencies(): array
    {
        $currencies = \Settings::get('dress.multistore.currencies', []);

        $ebabled_currencies = array_filter($currencies, function($item) {
            return !isset($item['enabled']) || $item['enabled'] !== false? true: false;
        });

        return $ebabled_currencies;
    }


    public static function currencyOptions(): array
    {
        $values = self::currencies();
        $currencies = array_column($values, 'name', 'code');

        return $currencies;
    }
    
    public static function countryCurrency(string $countryCode = null): string|null
    {
        $countryCode = $countryCode ?? self::context()->country;
        $countries = self::countries();
        
        if (isset($countries[$countryCode])) {
            return $countries[$countryCode]['currency'] ?? null;
        }
        
        return null;
    }

    public static function countryLabel(string $countryCode = null): string
    {
        $countryCode = $countryCode ?? self::context()->country;
        $countryCode = strtolower($countryCode);
        $countries = self::countries();
        
        if (isset($countries[$countryCode])) {
            return $countries[$countryCode]['country'] ?? $countryCode;
        }
        
        return $countryCode;
    }

    public static function getGlobalRegionUnit(): array
    {
        return [
            'enabled' => true,
            'country' => 'Global',
            'locale' => 'en',
            'code' => self::globalRegionCode(),
            'currency' => 'USD',
            'delivery' => [],
            'payment' => []
        ];
    }
}
