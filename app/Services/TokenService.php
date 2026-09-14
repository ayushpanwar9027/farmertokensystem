<?php

declare(strict_types=1);

namespace App\Services;

class TokenService
{
    public function generate(int $bytes = 64): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public function generateRefreshToken(): string
    {
        return $this->generate(64);
    }

    public function generateRememberToken(): string
    {
        return $this->generate(64);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateVerificationId(string $prefix = 'ver'): string
    {
        return $prefix . '_' . bin2hex(random_bytes(6));
    }

    public function generateRegistrationToken(): string
    {
        return 'reg_' . bin2hex(random_bytes(8));
    }

    public function generateJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function qrData(string $displayToken): string
    {
        return 'FPS-TOKEN:' . $displayToken;
    }
}
