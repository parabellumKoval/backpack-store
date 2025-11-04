<?php

namespace Backpack\Store\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddXRegionHeadersToRequest
{
    public function handle(Request $request, Closure $next)
    {
        $region = $request->header('X-Region');

        if ($region !== null && $region !== '') {
            $request->merge([
                'country' => trim($region),
                // 'country' => strtoupper(trim($region)),
            ]);
        }

        return $next($request);
    }
}
