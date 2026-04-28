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

        $statusCode = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
        if ($statusCode < 400) {
            return $response;
        }

        $responseContent = method_exists($response, 'getContent') ? $response->getContent() : null;
        $decodedResponse = is_string($responseContent) && strlen($responseContent) <= 262_144
            ? json_decode($responseContent)
            : null;

        $metaCode = $decodedResponse->meta->code ?? $statusCode;
        $message = $decodedResponse->meta->message ?? $decodedResponse->message ?? 'Unknown Error';

        $requestBody = $request->all();

        // Security: Filter out sensitive fields
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'pin',
            'old_password',
            'new_password',
            'otp',
            'whatsapp_number',
            'nomor_whatsapp',
        ];
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
            'RESPONSE CODE' => $metaCode,
            'MESSAGE STATUS' => $message,
            // 'FULL RESPONSE' => $responseContent, // Optional: Uncomment if full response logging is needed, but be careful with PII
        ];
        Log::channel('custom')->info('API Request Log', $log);

        return $response;
    }
}
