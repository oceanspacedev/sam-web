<?php

namespace App\Helpers;

/**
 * API Error Response Formatter
 *
 * Provides a standardized error response format for API endpoints.
 * All success responses now use Laravel API Resources or response()->json() directly.
 *
 * @see App\Http\Resources For success response formatting via API Resources
 */
class ResponseFormatter
{
    /**
     * Format and return a standardized error response
     *
     * @param  mixed  $data  Error details or validation errors
     * @param  string|null  $message  Human-readable error message
     * @param  int  $code  HTTP status code (default: 400)
     * @return \Illuminate\Http\JsonResponse
     *
     * @example
     * // Validation error (422)
     * return ResponseFormatter::error(
     *     ['field' => ['Field is required']],
     *     'Validation error',
     *     422
     * );
     * // Returns: { meta: {...}, data: null, errors: { field: [...] } }
     *
     * // General error (404, 401, etc)
     * return ResponseFormatter::error(
     *     null,
     *     'Not found',
     *     404
     * );
     * // Returns: { meta: {...}, data: null, errors: null }
     */
    public static function error($data = null, $message = null, $code = 400)
    {
        // For validation errors (422), move data to errors field
        // For other errors, both data and errors are null
        $errors = null;
        $responseData = null;

        if ($code === 422 && is_array($data) && ! empty($data)) {
            // Validation error: move to errors field
            $errors = $data;
        } elseif ($data !== null && $code !== 422) {
            // Non-validation error with data: keep in data field
            $responseData = $data;
        }

        $payload = [
            'meta' => [
                'code' => $code,
                'status' => 'error',
                'message' => $message,
            ],
            'data' => $responseData,
            'errors' => $errors,
        ];

        return response()->json($payload, $code);
    }
}
