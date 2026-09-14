<?php

declare(strict_types=1);

namespace App\Validators;

class AuthValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function register(array $data): array
    {
        return $this->validator->validate($data, [
            'mobile' => 'required|mobile',
            'device_id' => 'nullable|string|max:100',
        ]);
    }

    public function verifyOtp(array $data): array
    {
        return $this->validator->validate($data, [
            'verification_id' => 'required|string|max:40',
            'otp' => 'required|digits:6',
        ]);
    }

    public function resendOtp(array $data): array
    {
        return $this->validator->validate($data, [
            'verification_id' => 'required|string|max:40',
        ]);
    }

    public function completeRegistration(array $data): array
    {
        return $this->validator->validate($data, [
            'registration_token' => 'required|string|min:16',
            'name' => 'required|string|max:191',
            'password' => 'required|strong_password|confirmed',
            'password_confirmation' => 'required',
            'village' => 'required|string|max:190',
            'district_id' => 'required|int|exists:districts,id',
            'state' => 'required|string|max:100',
            'pincode' => 'nullable|digits:6',
            'land_area_acres' => 'nullable|numeric',
            'primary_crops' => 'nullable|array_type',
            'aadhaar_last4' => 'nullable|digits:4',
        ]);
    }

    public function appLogin(array $data): array
    {
        return $this->validator->validate($data, [
            'mobile' => 'required|mobile',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:190',
            'platform' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:20',
            'remember_me' => 'nullable|boolean',
        ]);
    }

    public function webLogin(array $data): array
    {
        return $this->validator->validate($data, [
            'username' => 'nullable|string|max:50',
            'mobile' => 'nullable|mobile',
            'password' => 'required|string',
            'remember_me' => 'nullable|boolean',
        ]);
    }

    public function logout(array $data): array
    {
        return $this->validator->validate($data, [
            'refresh_token' => 'nullable|string',
            'revoke_all_devices' => 'nullable|boolean',
        ]);
    }

    public function refresh(array $data): array
    {
        return $this->validator->validate($data, [
            'refresh_token' => 'required|string',
        ]);
    }

    public function forgotPassword(array $data): array
    {
        return $this->validator->validate($data, [
            'mobile' => 'required|mobile',
        ]);
    }

    public function resetPassword(array $data): array
    {
        return $this->validator->validate($data, [
            'reset_id' => 'required|string|max:40',
            'otp' => 'required|digits:6',
            'password' => 'required|strong_password|confirmed',
            'password_confirmation' => 'required',
        ]);
    }

    public function changePassword(array $data): array
    {
        return $this->validator->validate($data, [
            'current_password' => 'required|string',
            'new_password' => 'required|strong_password|confirmed',
            'new_password_confirmation' => 'required',
        ]);
    }

    public function verify2fa(array $data): array
    {
        return $this->validator->validate($data, [
            'verification_id' => 'required|string|max:40',
            'otp' => 'required|digits:6',
            'device_id' => 'nullable|string|max:100',
            'device_name' => 'nullable|string|max:190',
            'platform' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:20',
            'remember_me' => 'nullable|boolean',
        ]);
    }

    public function resend2fa(array $data): array
    {
        return $this->validator->validate($data, [
            'verification_id' => 'required|string|max:40',
        ]);
    }

    public function enable2fa(array $data): array
    {
        return $this->validator->validate($data, []);
    }

    public function confirm2fa(array $data): array
    {
        return $this->validator->validate($data, [
            'verification_id' => 'required|string|max:40',
            'otp' => 'required|digits:6',
            'purpose' => 'nullable|string|max:50',
        ]);
    }

    public function registerDevice(array $data): array
    {
        return $this->validator->validate($data, [
            'device_id' => 'required|string|max:100',
            'device_name' => 'nullable|string|max:190',
            'platform' => 'nullable|string|max:30',
            'app_version' => 'nullable|string|max:20',
            'onesignal_player_id' => 'nullable|string|max:190',
        ]);
    }
}