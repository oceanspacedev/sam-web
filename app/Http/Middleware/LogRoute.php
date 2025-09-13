<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogRoute
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if (json_decode($response->getContent())->meta->code != 200) {
            $log = [
                'REQUESTBY' => Auth::user()->nama_lengkap,
                'URI' => $request->getUri(),
                'METHOD' => $request->getMethod(),
                'REQUEST_BODY' => $request->all(),
                'RESPONSE CODE' => json_decode($response->getContent())->meta->code,
                'MESSAGE STATUS' => json_decode($response->getContent())->meta->message,
                'FULL RESPONSE' => $response->getContent(),
            ];
            Log::channel('custom')->info($log);
        }

        return $response;
    }
}
