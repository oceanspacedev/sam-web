<?php

namespace App\Exceptions;

use App\Exceptions\Api\ApiException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            // Log API errors with context
            if (request()->is('api/*') && ! $e instanceof ValidationException) {
                Log::error('API Error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_id' => auth()->id(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        });

        // Custom API exceptions
        $this->renderable(function (ApiException $e, $request) {
            return $e->render($request);
        });
    }

    /**
     * Convert a validation exception into a JSON response.
     * This ensures validation errors follow our standardized format.
     */
    protected function invalidJson($request, ValidationException $exception)
    {
        return response()->json([
            'meta' => [
                'code' => $exception->status,
                'status' => 'error',
                'message' => 'Validation error',
            ],
            'data' => null,
            'errors' => $exception->errors(),
        ], $exception->status);
    }

    /**
     * Render an exception into an HTTP response.
     * This standardizes all API error responses.
     */
    /**
     * Convert an authentication exception into a JSON response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
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
    }

    public function render($request, Throwable $e)
    {
        // Only customize for API requests
        if ($request->is('api/*') || $request->expectsJson()) {
            // Handle rate limiting (Too Many Attempts)
            if ($e instanceof ThrottleRequestsException) {
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

            // Handle Eloquent Model Not Found (e.g., findOrFail())
            if ($e instanceof ModelNotFoundException) {
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

            // Handle HTTP exceptions (404, 403, etc from abort())
            if ($e instanceof HttpException) {
                $statusCode = $e->getStatusCode();
                $message = $e->getMessage() ?: $this->getDefaultMessage($statusCode);

                return response()->json([
                    'meta' => [
                        'code' => $statusCode,
                        'status' => 'error',
                        'message' => $message,
                    ],
                    'data' => null,
                    'errors' => null,
                ], $statusCode);
            }

            // Handle RuntimeException (file upload, etc)
            if ($e instanceof RuntimeException) {
                return response()->json([
                    'meta' => [
                        'code' => 422,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ],
                    'data' => null,
                    'errors' => null,
                ], 422);
            }

            // Handle all other exceptions (500)
            return response()->json([
                'meta' => [
                    'code' => 500,
                    'status' => 'error',
                    'message' => app()->environment('production')
                        ? 'Terjadi kesalahan, silakan coba lagi'
                        : $e->getMessage(),
                ],
                'data' => null,
                'errors' => null,
            ], 500);
        }

        // Let parent handle non-API requests
        return parent::render($request, $e);
    }

    /**
     * Get default message for common HTTP status codes
     */
    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not found',
            405 => 'Method not allowed',
            429 => 'Too many requests',
            500 => 'Server error',
            503 => 'Service unavailable',
            default => 'Error occurred',
        };
    }
}
