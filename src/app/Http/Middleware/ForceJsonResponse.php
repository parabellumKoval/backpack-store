<?php
namespace Backpack\Store\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // заставляем ValidationException и прочие ответы быть JSON
        $request->headers->set('Accept', 'application/json');
        return $next($request);
    }
}
