<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TestUploadController extends Controller
{
    public function __invoke(Request $request, FileUploadService $fileUpload): JsonResponse
    {
        $key = sprintf('test-upload:%s', $request->user()?->id ?? $request->ip());

        $allowed = RateLimiter::attempt(
            $key,
            25,
            function () use ($request, $fileUpload) {
                if ($request->hasFile('file')) {
                    $fileUpload->uploadImageOptimized(
                        $request->file('file'),
                        $request->input('type', 'photo')
                    );
                }

                return true;
            },
            60
        );

        if (! $allowed) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Rate limit exceeded',
                'retry_after' => $retryAfter,
            ], 429);
        }

        return response()->json([
            'message' => 'Upload accepted',
        ]);
    }
}
