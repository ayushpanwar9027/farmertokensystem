<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Models\OtpVerification;

class OtpService
{
    private int $expirySeconds = 300;
    private int $resendCooldown = 60;
    private int $maxAttempts = 5;
    private int $maxResends = 3;

    public function __construct()
    {
        $this->maxAttempts = max(1, (int) getenv('OTP_MAX_ATTEMPTS') ?: 5);
        $this->loadTimingConfig();
    }

    private function loadTimingConfig(): void
    {
        $expiryMinutes = get_setting('otp_expiry_minutes', null);
        if (is_numeric($expiryMinutes)) {
            $this->expirySeconds = max(30, (int) $expiryMinutes * 60);
        } else {
            $this->expirySeconds = max(30, (int) (getenv('OTP_EXPIRY') ?: 300));
        }

        $cooldown = get_setting('otp_resend_cooldown_seconds', null);
        if (is_numeric($cooldown)) {
            $this->resendCooldown = max(1, (int) $cooldown);
        } else {
            $this->resendCooldown = max(1, (int) (getenv('OTP_RESEND_COOLDOWN') ?: 60));
        }
    }

    public function generate(string $mobile, string $purpose): array
    {
        $rateLimit = (int) get_setting('rate_limit_otp_per_5min', 3);
        $rateLimiter = new RateLimiter();
        $rateKey = 'otp:' . $mobile;
        $result = $rateLimiter->check($rateKey, $rateLimit, 300);
        if ($result['exceeded']) {
            throw new RateLimitException($result['retry_after'], 'Too many OTP requests. Please try again later.');
        }
        $rateLimiter->increment($rateKey, 300);

        $otp = (string) random_int(100000, 999999);

        $prefix = match ($purpose) {
            'PASSWORD_RESET' => 'rst',
            'LOGIN_2FA', '2FA_ENABLE', '2FA_STEP_UP' => '2fa',
            default => 'ver',
        };
        $verificationId = (new TokenService())->generateVerificationId($prefix);
        $otpHash = $this->hashOtp($otp, $verificationId);

        $model = new OtpVerification();
        $model->insert([
            'mobile' => $mobile,
            'purpose' => $purpose,
            'verification_id' => $verificationId,
            'otp_hash' => $otpHash,
            'attempts' => 0,
            'max_attempts' => $this->maxAttempts,
            'expires_at' => date('Y-m-d H:i:s', time() + $this->expirySeconds),
            'resend_at' => date('Y-m-d H:i:s', time() + $this->resendCooldown),
        ]);

        (new NotificationService())->sendOtp($mobile, $otp, $purpose);

        $this->writeDevOtpLog($verificationId, $otp, $mobile, $purpose);

        return [
            'verification_id' => $verificationId,
            'expires_in' => $this->expirySeconds,
            'resend_after' => $this->resendCooldown,
        ];
    }

    private function writeDevOtpLog(string $verificationId, string $otp, string $mobile, string $purpose): void
    {
        if ((getenv('APP_ENV') ?: 'development') === 'production') {
            return;
        }

        $logPath = dirname(__DIR__, 2) . '/storage/.otp_log';
        $line = sprintf(
            "timestamp=%s verification_id=%s purpose=%s mobile=****%s otp=%s" . PHP_EOL,
            gmdate('c'),
            $verificationId,
            $purpose,
            substr($mobile, -4),
            $otp
        );
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }

    public function resend(string $verificationId): array
    {
        $model = new OtpVerification();
        $record = $model->byVerificationId($verificationId);

        if ($record === null) {
            throw new NotFoundException('OTP_NOT_FOUND', 'Verification not found');
        }

        if ($record['verified_at'] !== null) {
            throw new AuthenticationException('INVALID_OTP', 'Verification already completed', 400);
        }

        $now = time();
        if (strtotime((string) $record['expires_at']) < $now) {
            throw new AuthenticationException('OTP_EXPIRED', 'OTP has expired. Please register again.', 400);
        }

        $resendAt = $record['resend_at'] ? strtotime((string) $record['resend_at']) : 0;
        if ($now < $resendAt) {
            $remaining = $resendAt - $now;
            throw new AuthenticationException('OTP_RESEND_COOLDOWN', 'Please wait before requesting another OTP', 429, ['resend_after' => $remaining]);
        }

        $newOtp = (string) random_int(100000, 999999);
        $otpHash = $this->hashOtp($newOtp, $verificationId);

        Database::update(
            'otp_verifications',
            [
                'otp_hash' => $otpHash,
                'attempts' => 0,
                'expires_at' => date('Y-m-d H:i:s', time() + $this->expirySeconds),
                'resend_at' => date('Y-m-d H:i:s', time() + $this->resendCooldown),
            ],
            'id = ?',
            [(int) $record['id']]
        );

        (new NotificationService())->sendOtp((string) $record['mobile'], $newOtp, (string) $record['purpose']);

        return ['resend_after' => $this->resendCooldown];
    }

    public function verify(string $verificationId, string $otp, ?int $userId = null): void
    {
        $model = new OtpVerification();
        $record = $model->byVerificationId($verificationId);

        if ($record === null) {
            throw new AuthenticationException('INVALID_OTP', 'Invalid OTP', 400);
        }

        if ($record['verified_at'] !== null) {
            throw new AuthenticationException('INVALID_OTP', 'Verification already completed', 400);
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            throw new AuthenticationException('OTP_EXPIRED', 'OTP has expired', 400);
        }

        if ((int) $record['attempts'] >= (int) $record['max_attempts']) {
            throw new AuthenticationException('INVALID_OTP', 'Too many invalid OTP attempts', 429);
        }

        $hashed = $this->hashOtp($otp, (string) $record['verification_id']);

        if (!hash_equals((string) $record['otp_hash'], $hashed)) {
            Database::update(
                'otp_verifications',
                ['attempts' => (int) $record['attempts'] + 1],
                'id = ?',
                [(int) $record['id']]
            );
            throw new AuthenticationException('INVALID_OTP', 'Invalid OTP', 400);
        }

        Database::update(
            'otp_verifications',
            ['verified_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [(int) $record['id']]
        );

        if ($userId !== null) {
            Database::update('users', ['mobile_verified_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
        }
    }

    public function revokeForMobile(string $mobile): void
    {
        Database::query("DELETE FROM otp_verifications WHERE mobile = ?", [$mobile]);
    }

    private function hashOtp(string $otp, string $salt): string
    {
        return hash('sha256', $salt . $otp);
    }
}
