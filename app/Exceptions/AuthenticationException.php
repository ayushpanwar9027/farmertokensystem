<?php

declare(strict_types=1);

namespace App\Exceptions;

class AuthenticationException extends AppException
{
    protected string $errorCode = 'UNAUTHENTICATED';
    protected int $httpStatus = 401;

    private array $details;

    public function __construct(string $errorCode = 'UNAUTHENTICATED', string $message = 'Authentication required', int $httpStatus = 401, array $details = [])
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
