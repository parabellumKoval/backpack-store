<?php

namespace Backpack\Store\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXRegionHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $region = $this->normalizeCountry($request->header('X-Region'));
        $lang = $this->preferredLanguage($request->header('Accept-Language'));
        $storefront = $this->normalizeStorefront($request->header('X-Storefront') ?? $request->get('storefront'));

        $payload = [];

        if ($region) {
            $payload['country'] = $region;
        }

        if ($lang) {
            $payload['lang'] = $lang;
            app()->setLocale($lang);
        }

        if ($storefront) {
            $payload['storefront'] = $storefront;
        }

        if (!empty($payload)) {
            $request->merge($payload);
        }

        if ($this->regionalContextAvailable()) {
            app(\App\Support\RegionalContext::class)->hydrateFromRequest($request, $lang);
        }

        return $next($request);
    }

    protected function preferredLanguage(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        $parts = explode(',', $header);
        $primary = trim($parts[0] ?? '');

        if ($primary === '') {
            return null;
        }

        $primary = strtolower($primary);

        if (str_contains($primary, ';')) {
            $primary = substr($primary, 0, strpos($primary, ';'));
        }

        $segments = preg_split('/[-_]/', $primary);
        $language = $segments[0] ?? null;

        if (!$language) {
            return null;
        }

        $supported = (array) config('app.supported_locales', []);

        if (!empty($supported) && !in_array($language, $supported, true)) {
            return null;
        }

        return $language;
    }

    protected function normalizeCountry(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $cleaned = preg_replace('/[^a-zA-Z]/', '', $value);
        $code = substr($cleaned, 0, 2);

        return strlen($code) === 2 ? $code : null;
    }

    protected function normalizeStorefront(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $cleaned = strtolower(trim((string) $value));
        $cleaned = preg_replace('/[^a-z0-9_-]/', '', $cleaned);

        return $cleaned !== '' ? $cleaned : null;
    }

    protected function regionalContextAvailable(): bool
    {
        return class_exists(\App\Support\RegionalContext::class)
            && app()->bound(\App\Support\RegionalContext::class);
    }
}
