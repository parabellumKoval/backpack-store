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
            // Use the first preferred locale value
            $locale = substr($locale, 0, 2);
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
