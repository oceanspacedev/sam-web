<?php

use App\Exceptions\Api\ApiException;
use App\Http\Middleware\RateLimitUploads;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (deployed behind a load balancer / FrankenPHP).
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Web middleware group
        $middleware->web(append: [
            'throttle:global-web',
        ]);

        // API middleware group
        $middleware->api(append: [
            RateLimitUploads::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
            'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
            'can' => \Illuminate\Auth\Middleware\Authorize::class,
            'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
            'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'logku' => \App\Http\Middleware\LogRoute::class,
            'upload.limiter' => RateLimitUploads::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
        $middleware->redirectUsersTo('/admin');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Log API errors with context
        $exceptions->reportable(function (Throwable $e) {
            if (! request()->is('api/*') || $e instanceof ValidationException) {
                return;
            }

            $statusCode = null;

            if ($e instanceof ApiException) {
                $statusCode = $e->getStatusCode();
            } elseif ($e instanceof HttpException) {
                $statusCode = $e->getStatusCode();
            } elseif ($e instanceof AuthenticationException) {
                $statusCode = 401;
            }

            $logLevel = $statusCode !== null && $statusCode < 500 ? 'warning' : 'error';

            Log::log($logLevel, 'API Error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'user_id' => auth()->id(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'status_code' => $statusCode,
            ]);
        });

        // Custom API exceptions
        $exceptions->renderable(function (ApiException $e, Request $request) {
            return $e->render($request);
        });

        // Handle rate limiting (Too Many Attempts)
        $exceptions->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'meta' => [
                        'code' => 429,
                        'status' => 'error',
                        'message' => 'Too many requests. Please slow down.',
                    ],
                    'data' => null,
                    'errors' => null,
                ], 429);
            }
        });

        // Handle Eloquent Model Not Found
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'meta' => [
                        'code' => 404,
                        'status' => 'error',
                        'message' => 'Resource tidak ditemukan',
                    ],
                    'data' => null,
                    'errors' => null,
                ], 404);
            }
        });

        // Handle HTTP exceptions (404, 403, etc from abort()) - API only
        $exceptions->renderable(function (HttpException $e, Request $request) {
            // Only handle for API requests
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // Let Laravel handle web requests
            }

            $statusCode = $e->getStatusCode();
            $message = $e->getMessage() ?: match ($statusCode) {
                401 => 'Unauthenticated',
                403 => 'Forbidden',
                404 => 'Not found',
                405 => 'Method not allowed',
                429 => 'Too many requests',
                500 => 'Server error',
                503 => 'Service unavailable',
                default => 'Error occurred',
            };

            return response()->json([
                'meta' => [
                    'code' => $statusCode,
                    'status' => 'error',
                    'message' => $message,
                ],
                'data' => null,
                'errors' => null,
            ], $statusCode);
        });

        // Handle RuntimeException (file upload, etc) - API only
        $exceptions->renderable(function (RuntimeException $e, Request $request) {
            // Only handle for API requests
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // Let Laravel handle web requests with debug page
            }

            return response()->json([
                'meta' => [
                    'code' => 422,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ],
                'data' => null,
                'errors' => null,
            ], 422);
        });

        // Handle validation exceptions for API
        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'meta' => [
                        'code' => $e->status,
                        'status' => 'error',
                        'message' => 'Validation error',
                    ],
                    'data' => null,
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        // Handle authentication exceptions
        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'meta' => [
                        'code' => 401,
                        'status' => 'error',
                        'message' => 'Unauthenticated. Please login again.',
                    ],
                    'data' => null,
                    'errors' => null,
                ], 401);
            }

            return redirect()->guest(route('filament.admin.auth.login'));
        });

        // Don't report these exceptions
        $exceptions->dontReport([
            // Add exceptions you don't want reported
        ]);

        // Don't flash these inputs
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })
    ->create();
