<?php

declare(strict_types=1);

namespace App\Validators;

class SlotValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function create(array $data): array
    {
        return $this->validator->validate($data, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'date' => 'required|date',
            'start_time' => 'required|time',
            'end_time' => 'required|time',
            'capacity' => 'nullable|int|min:1',
            'status' => 'nullable|in:ACTIVE,INACTIVE,FULL,CANCELLED',
        ]);
    }

    public function update(array $data): array
    {
        return $this->validator->validate($data, [
            'date' => 'nullable|date',
            'start_time' => 'nullable|time',
            'end_time' => 'nullable|time',
            'capacity' => 'nullable|int|min:1',
            'status' => 'nullable|in:ACTIVE,INACTIVE,FULL,CANCELLED',
        ]);
    }

    public function listFilters(array $query): array
    {
        $this->validator->validate($query, [
            'centre_id' => 'nullable|int',
            'date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|in:ACTIVE,INACTIVE,FULL,CANCELLED',
            'page' => 'nullable|int',
            'per_page' => 'nullable|int',
        ]);
        return $query;
    }

    public function generate(array $data): array
    {
        return $this->validator->validate($data, [
            'date_from' => 'required|date',
            'date_to' => 'required|date',
            'centre_id' => 'nullable|int|exists:procurement_centres,id',
        ]);
    }
}
