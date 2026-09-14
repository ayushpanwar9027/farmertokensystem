<?php

declare(strict_types=1);

namespace App\Exceptions;

class ExternalServiceException extends AppException
{
    protected string $errorCode = 'EXTERNAL_SERVICE_ERROR';
    protected int $httpStatus = 502;

    public function __construct(string $message = 'External service error')
    {
        parent::__construct($message);
    }
}
