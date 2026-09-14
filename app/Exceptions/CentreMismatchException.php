<?php

declare(strict_types=1);

namespace App\Exceptions;

class CentreMismatchException extends AuthorizationException
{
    protected string $errorCode = 'CENTRE_MISMATCH';

    public function __construct(string $message = 'Resource belongs to another centre')
    {
        parent::__construct($message);
    }
}