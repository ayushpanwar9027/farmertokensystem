<?php

declare(strict_types=1);

namespace App\Exceptions;

class AppException extends \RuntimeException
{
    protected string $errorCode = 'SERVER_ERROR';
    protected int $httpStatus = 500;

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getDetails(): array
    {
        return [];
    }
}
