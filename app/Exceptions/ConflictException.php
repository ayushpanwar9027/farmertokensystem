<?php

declare(strict_types=1);

namespace App\Exceptions;

class ConflictException extends AppException
{
    protected string $errorCode = 'CONFLICT';
    protected int $httpStatus = 409;

    private array $details = [];

    public function __construct(string $errorCode = 'CONFLICT', string $message = 'Resource conflict', array $details = [])
    {
        $this->errorCode = $errorCode;
        $this->details = $details;
        parent::__construct($message);
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
