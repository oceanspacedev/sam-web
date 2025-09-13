<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! Auth::user()) {
            return redirect('/masuk');
        }
        if (auth()->user()->role->can_access_web == 1) {
            return $next($request);
        }

        return redirect('/masuk');
    }
}
