<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;
use App\Models\Role;

class StaffValidator
{
    private const CENTRE_ROLES = ['CENTRE_MANAGER', 'CENTRE_OPERATOR'];

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
            'email' => 'nullable|email|max:191',
            'username' => 'nullable|string|max:50',
            'role' => 'required|string|max:50',
            'centre_id' => 'nullable|int',
            'status' => 'nullable|in:ACTIVE,INACTIVE',
        ]);
    }

    public function update(array $data): array
    {
        return $this->validator->validate($data, [
            'name' => 'nullable|string|max:191',
            'mobile' => 'nullable|mobile',
            'email' => 'nullable|email|max:191',
            'username' => 'nullable|string|max:50',
        ]);
    }

    public function status(array $data): array
    {
        return $this->validator->validate($data, [
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);
    }

    public function centre(array $data): array
    {
        return $this->validator->validate($data, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
        ]);
    }

    public function listFilters(array $query): array
    {
        $this->validator->validate($query, [
            'q' => 'nullable|string|max:191',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|in:ACTIVE,INACTIVE,LOCKED,PENDING',
            'centre_id' => 'nullable|int',
            'district_id' => 'nullable|int',
            'page' => 'nullable|int',
            'per_page' => 'nullable|int',
        ]);
        return $query;
    }

    public function resolveRole(string $roleName): array
    {
        $role = (new Role())->byName(strtoupper(trim($roleName)));
        if ($role === null) {
            throw new ValidationException(['role' => ['The selected role is invalid']]);
        }
        return $role;
    }

    public function isCentreRole(string $roleName): bool
    {
        return in_array(strtoupper($roleName), self::CENTRE_ROLES, true);
    }
}