<?php

declare(strict_types=1);

namespace App\Validators;

class QueueValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function live(array $data): array
    {
        return $this->validator->validate($data, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'date' => 'nullable|date',
        ]);
    }

    public function callNext(array $data): array
    {
        return $this->validator->validate($data, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'date' => 'nullable|date',
        ]);
    }

    public function skip(array $data): array
    {
        return $this->validator->validate($data, [
            'reason' => 'required|string|min:2|max:500',
        ]);
    }

    public function noShow(array $data): array
    {
        return $this->validator->validate($data, [
            'notes' => 'nullable|string|max:500',
        ]);
    }

    public function recall(array $data): array
    {
        return $this->validator->validate($data, []);
    }

    public function listFilters(array $query): array
    {
        return $this->validator->validate($query, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'date' => 'nullable|date',
            'status' => 'nullable|in:WAITING,CALLED,IN_PROGRESS,COMPLETED,SKIPPED,NO_SHOW,CANCELLED',
        ]);
    }

    public function stats(array $query): array
    {
        return $this->validator->validate($query, [
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'date' => 'nullable|date',
        ]);
    }
}