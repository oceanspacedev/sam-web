<?php

namespace App\Helpers;

/**
 * Format response.
 */
class ResponseFormatter
{
    /**
     * Give success response.
     */
    public static function success($data = null, $message = null, $code = 200)
    {
        $payload = [
            'meta' => [
                'code' => $code,
                'status' => 'success',
                'message' => $message,
            ],
            'data' => $data,
        ];

        return response()->json($payload, $code);
    }

    /**
     * Give error response.
     */
    public static function error($data = null, $message = null, $code = 400)
    {
        $payload = [
            'meta' => [
                'code' => $code,
                'status' => 'error',
                'message' => $message,
            ],
            'data' => $data,
        ];

        return response()->json($payload, $code);
    }
}
