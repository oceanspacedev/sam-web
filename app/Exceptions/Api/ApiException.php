<?php

namespace App\Exceptions\Api;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Base exception for all API errors
 * Provides standardized JSON response format
 */
abstract class ApiException extends Exception
{
    protected int $statusCode = 500;

    protected ?string $userMessage = null;

    protected mixed $errorData = null;

    public function __construct(?string $message = null, mixed $data = null, ?\Throwable $previous = null)
    {
        $this->userMessage = $message;
        $this->errorData = $data;

        parent::__construct($message ?? $this->getDefaultMessage(), 0, $previous);
    }

    /**
     * Render the exception as an HTTP response
     */
    public function render($request): JsonResponse
    {
        return response()->json([
            'meta' => [
                'code' => $this->statusCode,
                'status' => 'error',
                'message' => $this->userMessage ?? $this->getDefaultMessage(),
            ],
            'data' => $this->errorData,
            'errors' => $this->getErrors(),
        ], $this->statusCode);
    }

    /**
     * Get the default error message for this exception type
     */
    abstract protected function getDefaultMessage(): string;

    /**
     * Get errors array (override in child classes if needed)
     */
    protected function getErrors(): mixed
    {
        return null;
    }

    /**
     * Set additional error data
     */
    public function withData(mixed $data): static
    {
        $this->errorData = $data;

        return $this;
    }
}
