<?php
namespace Backpack\Store\app\Services;

use Backpack\Store\app\Models\Settings;

class SettingsService
{
    protected array $cache = [];

    public function get(string $key, $default = null)
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $setting = Settings::where('key', $key)->first();

        if ($setting) {
            return $this->cache[$key] = $setting->value;
        }

        return $this->cache[$key] = config('store_settings.' . $key, $default);
    }

    public function set(string $key, $value): void
    {
        Settings::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->cache[$key] = $value;
    }
}
