<?php

namespace App\Exceptions\Api;

/**
 * Exception for file upload errors (422)
 * Use when file upload fails validation or processing
 */
class FileUploadException extends ApiException
{
    protected int $statusCode = 422;

    protected function getDefaultMessage(): string
    {
        return 'Gagal mengupload file';
    }
}
