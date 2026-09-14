<?php

declare(strict_types=1);

namespace App\Exceptions;

class MaintenanceException extends AppException
{
    protected string $errorCode = 'MAINTENANCE_MODE';
    protected int $httpStatus = 503;

    private string $expectedAvailableAt;

    public function __construct(string $message = 'System is under maintenance', string $expectedAvailableAt = '')
    {
        $this->expectedAvailableAt = $expectedAvailableAt;
        parent::__construct($message);
    }

    public function getExpectedAvailableAt(): string
    {
        return $this->expectedAvailableAt;
    }
}
