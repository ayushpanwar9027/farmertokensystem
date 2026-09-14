<?php

declare(strict_types=1);

namespace App\Exceptions;

class AuthorizationException extends AppException
{
    protected string $errorCode = 'FORBIDDEN';
    protected int $httpStatus = 403;

    public function __construct(string $message = 'You do not have permission to perform this action')
    {
        parent::__construct($message);
    }
}
