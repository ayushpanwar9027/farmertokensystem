<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\User;

class PasswordResetService
{
    private User $userModel;
    private OtpService $otpService;

    public function __construct()
    {
        $this->userModel = new User();
        $this->otpService = new OtpService();
    }

    public function forgot(string $mobile): array
    {
        $user = $this->userModel->byMobile($mobile);
        if ($user === null) {
            throw new NotFoundException('ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $otp = $this->otpService->generate($mobile, 'PASSWORD_RESET');

        return [
            'reset_id' => $otp['verification_id'],
            'resend_after' => $otp['resend_after'],
        ];
    }

    public function reset(string $resetId, string $otp, string $newPassword): void
    {
        $record = Database::selectOne(
            "SELECT mobile FROM otp_verifications WHERE verification_id = ? AND purpose = 'PASSWORD_RESET' LIMIT 1",
            [$resetId]
        );

        if ($record === null) {
            throw new AuthenticationException('INVALID_OTP', 'Invalid reset verification', 400);
        }

        $this->otpService->verify($resetId, $otp);

        $user = $this->userModel->byMobile((string) $record['mobile']);
        if ($user === null) {
            throw new NotFoundException('ACCOUNT_NOT_FOUND', 'Account not found');
        }

        (new AuthService())->resetPassword((int) $user['id'], $newPassword);
    }
}