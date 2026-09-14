<?php

declare(strict_types=1);

namespace App\Exceptions;

class DatabaseException extends AppException
{
    protected string $errorCode = 'DATABASE_ERROR';
    protected int $httpStatus = 500;

    public function __construct(string $message = 'A database error occurred', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
