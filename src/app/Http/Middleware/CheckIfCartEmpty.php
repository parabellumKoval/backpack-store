<?php

namespace Aimix\Shop\app\Http\Middleware;

use Closure;

class CheckIfCartEmpty
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
      if(session()->has('cart') && count(session()->get('cart')))
        return $next($request);

      return back()->with('message', __('main.cart_empty'))->with('type', 'error');
    }
}
