<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Route;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // Jangan redirect untuk API; biarkan 401 JSON dikembalikan
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Arahkan ke halaman login admin (Filament) untuk request web
        if (Route::has('filament.admin.auth.login')) {
            return route('filament.admin.auth.login');
        }

        // Fallback ke path standar
        return url('/admin/login');
    }
}
