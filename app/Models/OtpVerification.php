<?php

declare(strict_types=1);

namespace App\Models;

class OtpVerification extends BaseModel
{
    protected string $table = 'otp_verifications';
    protected bool $softDeletes = false;

    public function byVerificationId(string $verificationId): ?array
    {
        return $this->findBy('verification_id', $verificationId);
    }
}
