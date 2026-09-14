<?php

declare(strict_types=1);

namespace App\Exceptions;

class BadRequestException extends AppException
{
    protected string $errorCode = 'BAD_REQUEST';
    protected int $httpStatus = 400;

    private array $details;

    public function __construct(string $errorCode = 'BAD_REQUEST', string $message = 'Bad request', array $details = [])
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