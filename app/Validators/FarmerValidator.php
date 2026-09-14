<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Models\Role;

class FarmerValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function create(array $data): array
    {
        return $this->validator->validate($data, [
            'name' => 'required|string|max:191',
            'mobile' => 'required|mobile',
            'password' => 'nullable|string|min:8|max:128',
            'village' => 'required|string|max:190',
            'district_id' => 'required|int|exists:districts,id',
            'state' => 'nullable|string|max:100',
            'alternative_mobile' => 'nullable|mobile',
            'land_area_acres' => 'nullable|numeric',
            'primary_crops' => 'nullable|array_type',
            'aadhaar_last4' => 'nullable|digits:4',
        ]);
    }

    public function password(array $data): array
    {
        return $this->validator->validate($data, [
            'password' => 'required|string|min:8|max:128',
        ]);
    }

    public function status(array $data): array
    {
        return $this->validator->validate($data, [
            'status' => 'required|in:ACTIVE,INACTIVE,LOCKED',
        ]);
    }

    public function listFilters(array $query): array
    {
        $this->validator->validate($query, [
            'q' => 'nullable|string|max:191',
            'district_id' => 'nullable|int',
            'status' => 'nullable|in:ACTIVE,INACTIVE,LOCKED,PENDING',
            'verification_status' => 'nullable|in:PENDING,APPROVED,REJECTED',
            'page' => 'nullable|int',
            'per_page' => 'nullable|int',
        ]);
        return $query;
    }
}