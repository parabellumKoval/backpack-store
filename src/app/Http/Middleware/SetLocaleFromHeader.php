<?php

namespace Backpack\Store\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if ($locale) {
            $primary = trim(explode(',', $locale)[0] ?? '');

            if ($primary !== '') {
                $primary = strtolower($primary);

                if (str_contains($primary, ';')) {
                    $primary = substr($primary, 0, strpos($primary, ';'));
                }

                $segments = preg_split('/[-_]/', $primary);
                $language = $segments[0] ?? null;

                $supported = (array) config('app.supported_locales', []);

                if ($language && (empty($supported) || in_array($language, $supported, true))) {
                    app()->setLocale($language);
                }
            }
        }

        return $next($request);
    }
}
