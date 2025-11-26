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
        $responseContent = $response->getContent();
        $decodedResponse = json_decode($responseContent);

        if (isset($decodedResponse->meta->code) && $decodedResponse->meta->code != 200) {
            $requestBody = $request->all();

            // Security: Filter out sensitive fields
            $sensitiveFields = ['password', 'password_confirmation', 'pin', 'old_password', 'new_password'];
            foreach ($sensitiveFields as $field) {
                if (isset($requestBody[$field])) {
                    $requestBody[$field] = '***REDACTED***';
                }
            }

            $log = [
                'REQUESTBY' => Auth::user()?->nama_lengkap ?? 'Guest',
                'URI' => $request->getUri(),
                'METHOD' => $request->getMethod(),
                'REQUEST_BODY' => $requestBody,
                'RESPONSE CODE' => $decodedResponse->meta->code,
                'MESSAGE STATUS' => $decodedResponse->meta->message ?? 'Unknown Error',
                // 'FULL RESPONSE' => $responseContent, // Optional: Uncomment if full response logging is needed, but be careful with PII
            ];
            Log::channel('custom')->info('API Request Log', $log);
        }

        return $response;
    }
}
