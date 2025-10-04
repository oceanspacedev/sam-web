<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RateLimitUploads
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply to routes with file uploads
        if (!$this->hasFileUpload($request)) {
            return $next($request);
        }

        $ipAddress = $request->ip();
        $userId = auth()->id();

        // Different limits for authenticated vs anonymous users
        $limitPerMinute = $userId ? 30 : 10; // 30 uploads per minute for authenticated, 10 for anonymous
        $limitPerHour = $userId ? 200 : 50;   // 200 uploads per hour for authenticated, 50 for anonymous

        // Check per-minute rate limit
        $minuteKey = "upload:minute:{$ipAddress}:" . ($userId ?? 'guest');
        $minuteCount = Redis::incr($minuteKey);

        if ($minuteCount === 1) {
            Redis::expire($minuteKey, 60); // Expire after 1 minute
        }

        if ($minuteCount > $limitPerMinute) {
            Log::warning('Rate limit exceeded (per minute)', [
                'ip' => $ipAddress,
                'user_id' => $userId,
                'count' => $minuteCount,
                'limit' => $limitPerMinute,
            ]);

            return response()->json([
                'message' => 'Too many upload attempts. Please try again later.',
                'retry_after' => 60,
                'limit' => $limitPerMinute,
            ], 429);
        }

        // Check per-hour rate limit
        $hourKey = "upload:hour:{$ipAddress}:" . ($userId ?? 'guest');
        $hourCount = Redis::incr($hourKey);

        if ($hourCount === 1) {
            Redis::expire($hourKey, 3600); // Expire after 1 hour
        }

        if ($hourCount > $limitPerHour) {
            Log::warning('Rate limit exceeded (per hour)', [
                'ip' => $ipAddress,
                'user_id' => $userId,
                'count' => $hourCount,
                'limit' => $limitPerHour,
            ]);

            return response()->json([
                'message' => 'Hourly upload limit exceeded. Please try again later.',
                'retry_after' => 3600,
                'limit' => $limitPerHour,
            ], 429);
        }

        // Add security headers
        $response = $next($request);

        $response->headers->set('X-Upload-Limit-Remaining', max(0, $limitPerMinute - $minuteCount));
        $response->headers->set('X-Upload-Limit-Reset', Redis::ttl($minuteKey));

        return $response;
    }

    /**
     * Check if request contains file uploads
     */
    protected function hasFileUpload(Request $request): bool
    {
        return $request->hasFile('file') ||
               $request->hasFile('video') ||
               $request->hasFile('photo') ||
               $request->hasFile('picture') ||
               $request->hasFile('poto_shop_sign') ||
               $request->hasFile('poto_depan') ||
               $request->hasFile('poto_kiri') ||
               $request->hasFile('poto_kanan') ||
               $request->hasFile('poto_ktp') ||
               $request->hasFile('picture_visit_in') ||
               $request->hasFile('picture_visit_out') ||
               $request->is('api/*'); // Apply to all API routes as safety net
    }
}