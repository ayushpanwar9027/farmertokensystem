<?php

declare(strict_types=1);

namespace App\Validators;

class CentreValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function create(array $data): array
    {
        return $this->validator->validate($data, [
            'name' => 'required|string|max:190',
            'code' => 'required|string|max:20|regex:/^[A-Z0-9]{2,10}$/',
            'district_id' => 'required|int|exists:districts,id',
            'address' => 'required|string|max:500',
            'contact_phone' => 'nullable|mobile',
            'contact_email' => 'nullable|email|max:191',
            'working_hours_start' => 'required|time',
            'working_hours_end' => 'required|time',
            'working_days' => 'nullable|string|max:50',
            'daily_capacity' => 'required|int|min:1',
            'slot_duration_minutes' => 'nullable|int|min:5',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'status' => 'nullable|in:ACTIVE,INACTIVE,CLOSED',
        ]);
    }

    public function update(array $data): array
    {
        return $this->validator->validate($data, [
            'name' => 'nullable|string|max:190',
            'code' => 'nullable|string|max:20|regex:/^[A-Z0-9]{2,10}$/',
            'district_id' => 'nullable|int|exists:districts,id',
            'address' => 'nullable|string|max:500',
            'contact_phone' => 'nullable|mobile',
            'contact_email' => 'nullable|email|max:191',
            'working_hours_start' => 'nullable|time',
            'working_hours_end' => 'nullable|time',
            'working_days' => 'nullable|string|max:50',
            'daily_capacity' => 'nullable|int|min:1',
            'slot_duration_minutes' => 'nullable|int|min:5',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
    }

    public function status(array $data): array
    {
        return $this->validator->validate($data, [
            'status' => 'required|in:ACTIVE,INACTIVE,CLOSED',
        ]);
    }

    public function listFilters(array $query): array
    {
        $this->validator->validate($query, [
            'q' => 'nullable|string|max:190',
            'district_id' => 'nullable|int',
            'status' => 'nullable|in:ACTIVE,INACTIVE,CLOSED',
            'page' => 'nullable|int',
            'per_page' => 'nullable|int',
        ]);
        return $query;
    }
}