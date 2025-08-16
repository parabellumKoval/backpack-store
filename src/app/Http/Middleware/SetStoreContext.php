<?php

namespace Backpack\Store\app\Http\Middleware;

use Closure;
use Backpack\Store\app\Services\StoreContext;
use Backpack\Store\app\Services\Resolvers\StoreContextResolver;

class SetStoreContext
{
    public function __construct(private StoreContextResolver $resolver) {}

    public function handle($request, Closure $next)
    {
        app()->instance(StoreContext::class, $this->resolver->resolve());
        return $next($request);
    }
}
