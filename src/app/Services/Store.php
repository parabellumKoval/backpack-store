<?php

namespace Backpack\Store\app\Services;

use \Backpack\Store\app\Services\StoreContext;

class Store
{

    public static function context(): StoreContext
    {
        return app(StoreContext::class);
    }


    public static function withContext(string $country, string $currency, callable $callback)
    {
        $cls = StoreContext::class;
        $prev = app()->bound($cls) ? app($cls) : null;

        app()->instance($cls, new StoreContext($country, $currency));
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
        return \Settings::get('dress.store.catalog_table_cache', false);
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

