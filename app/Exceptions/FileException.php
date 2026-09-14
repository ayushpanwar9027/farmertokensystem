<?php

declare(strict_types=1);

namespace App\Exceptions;

class FileException extends AppException
{
    private array $details;

    public function __construct(string $errorCode, string $message, int $httpStatus = 400, array $details = [])
    {
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
        $this->details = $details;
        parent::__construct($message);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}