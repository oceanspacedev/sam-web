<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RateLimitUploads
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $this->hasFileUpload($request)) {
            return $next($request);
        }

        $ipAddress = $request->ip();
        $userId = auth()->id();

        $limitPerMinute = $userId ? 60 : 40;
        $limitPerHour = $userId ? 300 : 120;

        $minuteKey = "upload:minute:{$ipAddress}:".($userId ?? 'guest');
        $minuteCount = $this->incrementCounter($minuteKey, 60);

        if ($minuteCount > $limitPerMinute) {
            Log::warning('Rate limit exceeded (per minute)', [
                'ip' => $ipAddress,
                'user_id' => $userId,
                'count' => $minuteCount,
                'limit' => $limitPerMinute,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 429,
                    'status' => 'error',
                    'message' => 'Too many upload attempts. Please try again later.',
                ],
                'data' => [
                    'retry_after' => 60,
                    'limit' => $limitPerMinute,
                ],
                'errors' => null,
            ], 429);
        }

        $hourKey = "upload:hour:{$ipAddress}:".($userId ?? 'guest');
        $hourCount = $this->incrementCounter($hourKey, 3600);

        if ($hourCount > $limitPerHour) {
            Log::warning('Rate limit exceeded (per hour)', [
                'ip' => $ipAddress,
                'user_id' => $userId,
                'count' => $hourCount,
                'limit' => $limitPerHour,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 429,
                    'status' => 'error',
                    'message' => 'Hourly upload limit exceeded. Please try again later.',
                ],
                'data' => [
                    'retry_after' => 3600,
                    'limit' => $limitPerHour,
                ],
                'errors' => null,
            ], 429);
        }

        $response = $next($request);

        $response->headers->set('X-Upload-Limit-Remaining', max(0, $limitPerMinute - $minuteCount));
        $response->headers->set('X-Upload-Limit-Reset', $this->counterTtl($minuteKey, 60));

        return $response;
    }

    protected function incrementCounter(string $key, int $ttlSeconds): int
    {
        try {
            $count = (int) Redis::incr($key);

            if ($count === 1) {
                Redis::expire($key, $ttlSeconds);
            }

            return $count;
        } catch (Throwable $exception) {
            Log::warning('Upload rate limiter falling back to cache store', [
                'key' => $key,
                'message' => $exception->getMessage(),
            ]);
        }

        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->addSeconds($ttlSeconds));

            return 1;
        }

        $count = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $count, now()->addSeconds($ttlSeconds));

        return $count;
    }

    protected function counterTtl(string $key, int $fallbackSeconds): int
    {
        try {
            $ttl = (int) Redis::ttl($key);

            return $ttl > 0 ? $ttl : $fallbackSeconds;
        } catch (Throwable) {
            return $fallbackSeconds;
        }
    }

    protected function hasFileUpload(Request $request): bool
    {
        $fields = [
            'file',
            'video',
            'photo',
            'picture',
            'profile_photo',
            'poto_shop_sign',
            'poto_depan',
            'poto_kiri',
            'poto_kanan',
            'poto_ktp',
            'picture_visit',
            'picture_visit_in',
            'picture_visit_out',
            'photo0',
            'photo1',
            'photo2',
            'photo3',
            'photo4',
        ];

        foreach ($fields as $field) {
            if ($request->hasFile($field)) {
                return true;
            }
        }

        return false;
    }
}
