<?php

namespace App\Exceptions\Api;

/**
 * Exception for unauthorized access (401)
 * Use when user is not authenticated
 */
class UnauthorizedException extends ApiException
{
    protected int $statusCode = 401;

    protected function getDefaultMessage(): string
    {
        return 'Unauthorized';
    }
}
