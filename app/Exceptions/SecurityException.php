<?php

declare(strict_types=1);

namespace App\Exceptions;

class SecurityException extends AppException
{
    protected string $errorCode = 'SECURITY_ERROR';
    protected int $httpStatus = 500;

    public function __construct(string $message = 'A security error occurred')
    {
        parent::__construct($message);
    }
}