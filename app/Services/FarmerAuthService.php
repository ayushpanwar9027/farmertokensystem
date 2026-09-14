<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ConflictException;
use App\Models\Farmer;
use App\Models\User;

class FarmerAuthService
{
    private TokenService $tokenService;
    private User $userModel;

    public function __construct()
    {
        $this->tokenService = new TokenService();
        $this->userModel = new User();
    }

    public function register(string $mobile): array
    {
        $mobile = $this->normalizeMobile($mobile);

        $existing = $this->userModel->byMobile($mobile);
        if ($existing !== null) {
            throw new ConflictException('ALREADY_REGISTERED', 'An account with this mobile number already exists. Please login.');
        }

        $otpService = new OtpService();
        $otp = $otpService->generate($mobile, 'REGISTER');

        return [
            'verification_id' => $otp['verification_id'],
            'otp_required' => true,
            'resend_after' => $otp['resend_after'],
            'existing_user' => false,
        ];
    }

    public function verifyOtp(string $verificationId, string $otp): array
    {
        $otpService = new OtpService();
        $otpService->verify($verificationId, $otp);

        $registrationToken = $this->issueRegistrationToken($verificationId);

        return [
            'verified' => true,
            'registration_token' => $registrationToken,
        ];
    }

    public function completeRegistration(array $data): array
    {
        $registrationToken = $data['registration_token'];
        $mobile = $this->mobileByRegistrationToken($registrationToken);

        if ($mobile === null) {
            throw new AuthenticationException('TOKEN_INVALID', 'Registration token is invalid or expired. Please register again.', 401);
        }

        $existing = $this->userModel->byMobile($mobile);
        if ($existing !== null) {
            throw new ConflictException('ALREADY_REGISTERED', 'An account already exists for this mobile number');
        }

        $autoApprove = (bool) get_setting('auto_approve_farmers', true);
        $verificationStatus = $autoApprove ? 'APPROVED' : 'PENDING';
        $status = $autoApprove ? 'ACTIVE' : 'PENDING';

        $roleId = $this->farmerRoleId();

        Database::beginTransaction();

        try {
            $userId = $this->userModel->insert([
                'name' => $data['name'],
                'mobile' => $mobile,
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role_id' => $roleId,
                'status' => $status,
                'verification_status' => $verificationStatus,
                'mobile_verified_at' => date('Y-m-d H:i:s'),
                'password_set_at' => date('Y-m-d H:i:s'),
            ]);

            $farmer = new Farmer();
            $farmerId = $farmer->insert([
                'user_id' => $userId,
                'village' => $data['village'],
                'district_id' => (int) $data['district_id'],
                'state' => $data['state'],
                'pincode' => $data['pincode'] ?? null,
                'land_area_acres' => isset($data['land_area_acres']) ? (float) $data['land_area_acres'] : null,
                'primary_crops' => isset($data['primary_crops']) ? json_encode($data['primary_crops'], JSON_UNESCAPED_UNICODE) : null,
                'aadhaar_last4' => $data['aadhaar_last4'] ?? null,
                'verification_status' => $verificationStatus,
                'verified_at' => $autoApprove ? date('Y-m-d H:i:s') : null,
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $user = $this->userModel->find($userId);
        $user['role'] = $this->userModel->roleName((int) $user['role_id']);

        return [
            'farmer' => [
                'id' => (int) $farmerId,
                'name' => $user['name'],
                'mobile' => $user['mobile'],
                'status' => $verificationStatus,
            ],
            'message' => $autoApprove
                ? 'Registration complete. Your account is active.'
                : 'Registration submitted for verification',
        ];
    }

    private function mobileByRegistrationToken(string $token): ?string
    {
        if (!preg_match('/^reg_/', $token)) {
            return null;
        }

        $secret = (string) (getenv('JWT_SECRET') ?: '');
        $payloadRaw = substr($token, 4);
        $parts = explode('.', $payloadRaw);
        if (count($parts) !== 2) {
            return null;
        }

        $decoded = base64_decode(strtr($parts[0], '-_', '+/'));
        $expected = hash_hmac('sha256', $parts[0], $secret);
        if (!hash_equals($expected, $parts[1])) {
            return null;
        }

        $payload = json_decode((string) $decoded, true);
        if (!is_array($payload) || !isset($payload['verification_id'], $payload['mobile'])) {
            return null;
        }

        $record = Database::selectOne(
            "SELECT id, verified_at, expires_at FROM otp_verifications WHERE verification_id = ? LIMIT 1",
            [(string) $payload['verification_id']]
        );

        if ($record === null || $record['verified_at'] === null) {
            return null;
        }

        if (strtotime((string) $record['expires_at']) < time()) {
            return null;
        }

        return (string) $payload['mobile'];
    }

    private function issueRegistrationToken(string $verificationId): string
    {
        $record = Database::selectOne(
            "SELECT mobile, expires_at FROM otp_verifications WHERE verification_id = ? LIMIT 1",
            [$verificationId]
        );

        if ($record === null) {
            throw new AuthenticationException('INVALID_OTP', 'Verification not found', 400);
        }

        $mobile = (string) $record['mobile'];
        $secret = (string) (getenv('JWT_SECRET') ?: '');

        $payload = base64_encode(json_encode([
            'verification_id' => $verificationId,
            'mobile' => $mobile,
            'expires_at' => (string) $record['expires_at'],
        ], JSON_UNESCAPED_UNICODE));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');

        $sig = hash_hmac('sha256', $payload, $secret);

        return 'reg_' . $payload . '.' . $sig;
    }

    private function farmerRoleId(): int
    {
        $row = Database::selectOne("SELECT id FROM roles WHERE name = 'FARMER' LIMIT 1");
        if ($row === null) {
            throw new \RuntimeException('FARMER role not seeded');
        }
        return (int) $row['id'];
    }

    private function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/[\s\-]/', '', $mobile);
        if (str_starts_with($mobile, '+91')) {
            $mobile = substr($mobile, 3);
        } elseif (str_starts_with($mobile, '91') && strlen($mobile) === 12) {
            $mobile = substr($mobile, 2);
        }
        return $mobile;
    }
}