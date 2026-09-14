<?php

declare(strict_types=1);

namespace App\Exceptions;

class ValidationException extends AppException
{
    protected string $errorCode = 'VALIDATION_ERROR';
    protected int $httpStatus = 400;

    private array $errors;

    public function __construct(array $errors, string $message = 'The given data was invalid')
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getDetails(): array
    {
        return $this->errors;
    }
}
