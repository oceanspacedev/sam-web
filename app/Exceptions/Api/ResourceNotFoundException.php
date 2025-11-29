<?php

namespace App\Exceptions\Api;

/**
 * Exception for resource not found (404)
 * Use when a specific resource (outlet, visit, etc) is not found
 */
class ResourceNotFoundException extends ApiException
{
    protected int $statusCode = 404;

    protected function getDefaultMessage(): string
    {
        return 'Resource tidak ditemukan';
    }
}
