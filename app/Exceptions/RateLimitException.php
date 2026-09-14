<?php

declare(strict_types=1);

namespace App\Exceptions;

class RateLimitException extends AppException
{
    protected string $errorCode = 'RATE_LIMIT_EXCEEDED';
    protected int $httpStatus = 429;

    private int $retryAfter;

    public function __construct(int $retryAfter = 60, string $message = 'Too many requests')
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getDetails(): array
    {
        return ['retry_after' => $this->retryAfter];
    }
}
