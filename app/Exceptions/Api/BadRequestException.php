<?php

namespace App\Exceptions\Api;

/**
 * Exception for bad request (400)
 * Use for business logic validation failures
 */
class BadRequestException extends ApiException
{
    protected int $statusCode = 400;

    protected function getDefaultMessage(): string
    {
        return 'Permintaan tidak valid';
    }
}
