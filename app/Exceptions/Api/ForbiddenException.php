<?php

namespace App\Exceptions\Api;

/**
 * Exception for forbidden access (403)
 * Use when user is authenticated but doesn't have permission
 */
class ForbiddenException extends ApiException
{
    protected int $statusCode = 403;

    protected function getDefaultMessage(): string
    {
        return 'Anda tidak memiliki akses untuk melakukan aksi ini';
    }
}
