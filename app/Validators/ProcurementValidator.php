<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\BadRequestException;
use App\Exceptions\ValidationException;

class ProcurementValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function capture(array $data): array
    {
        $validated = $this->validator->validate($data, [
            'accepted_weight' => 'required|numeric',
            'damaged_qty' => 'nullable|numeric',
            'grade' => 'nullable|in:A,B,C,REJECT',
            'moisture_pct' => 'nullable|numeric',
            'quality_notes' => 'nullable|string|max:500',
            'operator_note' => 'nullable|string|max:500',
        ]);

        if ((float) $validated['accepted_weight'] < 0) {
            throw new BadRequestException('WEIGHT_INVALID', 'Accepted weight cannot be negative');
        }

        if (isset($validated['damaged_qty']) && $validated['damaged_qty'] !== '' && (float) $validated['damaged_qty'] < 0) {
            throw new BadRequestException('WEIGHT_INVALID', 'Damaged quantity cannot be negative');
        }

        if (isset($validated['moisture_pct']) && $validated['moisture_pct'] !== '') {
            $moisture = (float) $validated['moisture_pct'];
            if ($moisture < 0 || $moisture > 100) {
                throw new BadRequestException('WEIGHT_INVALID', 'Moisture percentage must be between 0 and 100');
            }
        }

        if (array_key_exists('photo_ids', $data) && $data['photo_ids'] !== null && $data['photo_ids'] !== []) {
            if (!is_array($data['photo_ids'])) {
                throw new ValidationException(['photo_ids' => ['The photo ids must be an array']]);
            }
            $maxPhotos = 3;
            if (count($data['photo_ids']) > $maxPhotos) {
                throw new BadRequestException('WEIGHT_INVALID', 'Maximum ' . $maxPhotos . ' photos allowed');
            }
            $cleaned = [];
            foreach ($data['photo_ids'] as $pid) {
                if (!is_numeric($pid) || (int) $pid <= 0) {
                    throw new ValidationException(['photo_ids' => ['Each photo id must be a positive integer']]);
                }
                $cleaned[] = (int) $pid;
            }
            $validated['photo_ids'] = $cleaned;
        }

        return $validated;
    }

    public function submit(array $data): array
    {
        return $this->validator->validate($data, []);
    }

    public function reject(array $data): array
    {
        return $this->validator->validate($data, [
            'reason' => 'required|string|min:2|max:500',
        ]);
    }

    public function listFilters(array $query): array
    {
        return $this->validator->validate($query, [
            'status' => 'nullable|in:PENDING,PENDING_APPROVAL,VERIFIED,IN_PROGRESS,COMPLETED,REJECTED',
            'date' => 'nullable|date',
            'q' => 'nullable|string|max:100',
        ]);
    }

    public function approvalListFilters(array $query): array
    {
        return $this->validator->validate($query, [
            'centre_id' => 'nullable|int|exists:procurement_centres,id',
            'status' => 'nullable|in:PENDING_APPROVAL,VERIFIED,REJECTED',
        ]);
    }
}
