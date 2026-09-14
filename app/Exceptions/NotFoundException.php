<?php

declare(strict_types=1);

namespace App\Exceptions;

class NotFoundException extends AppException
{
    protected string $errorCode = 'NOT_FOUND';
    protected int $httpStatus = 404;

    private array $details;

    public function __construct(string $errorCode = 'NOT_FOUND', string $message = 'Resource not found', int $httpStatus = 404, array $details = [])
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
