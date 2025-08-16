<?php

namespace Backpack\Store\app\Services;

use \Backpack\Store\app\Services\StoreContext;

class Store
{

    public static function context(): StoreContext
    {
        return app(StoreContext::class);
    }
    
    public static function isMods(): bool
    {
        return config('bs.modifications.enabled', true);
    }

    public static function modMode(): string
    {
        return config('bs.modifications.mode', 'horizontal');
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
        return config('backpack.multistore.enabled', false);
    }

    public static function country(): string
    {
        return request()->get('country')
            ?? session('country')
            ?? config('backpack.multistore.default_country');
    }

    public static function currency(): string
    {
        return request()->get('currency')
            ?? session('currency')
            ?? config('backpack.multistore.default_currency');
    }

    // Получить все доступные страны (ты можешь заменить на свой способ)
    public static function countries(): array
    {
        $countries = config('backpack.multistore.countries', []);
        $enabled_countries = array_filter($countries, function($item) {
            return !isset($item['enabled']) || $item['enabled'] !== false? true: false;
        });

        return $enabled_countries;
    }

    // Получить все доступные валюты (например, как массив кодов)
    public static function currencies(): array
    {
        $currencies = config('backpack.multistore.currencies', []);

        $ebabled_currencies = array_filter($currencies, function($item) {
            return !isset($item['enabled']) || $item['enabled'] !== false? true: false;
        });

        return $ebabled_currencies;
    }
}

