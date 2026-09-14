<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

class BookingValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function create(array $data): array
    {
        $data = $this->validator->validate($data, [
            'booking_date' => 'required|date',
            'slot_id' => 'required|int|exists:slots,id',
            'centre_id' => 'required|int|exists:procurement_centres,id',
            'crops' => 'required|array_type',
        ]);

        $crops = $data['crops'] ?? [];
        $this->validateCrops($crops);

        return $data;
    }

    public function cancel(array $data): array
    {
        return $this->validator->validate($data, [
            'reason' => 'nullable|string|max:500',
        ]);
    }

    public function adminCancel(array $data): array
    {
        $data = $this->validator->validate($data, [
            'reason' => 'required|string|max:500',
        ]);
        return $data;
    }

    public function listFilters(array $query): array
    {
        $this->validator->validate($query, [
            'status' => 'nullable|in:PENDING,CONFIRMED,CANCELLED,COMPLETED,EXPIRED',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'centre_id' => 'nullable|int',
            'q' => 'nullable|string|max:100',
            'date' => 'nullable|date',
            'page' => 'nullable|int',
            'per_page' => 'nullable|int',
        ]);
        return $query;
    }

    private function validateCrops(array $crops): void
    {
        if (empty($crops)) {
            throw new ValidationException(['crops' => ['At least one crop is required']]);
        }

        $maxCrops = (int) (new \App\Services\SettingService())->getInt('max_crops_per_booking', 10);
        if (count($crops) > $maxCrops) {
            throw new ValidationException(['crops' => ['A booking can contain at most ' . $maxCrops . ' crops']]);
        }

        $seen = [];
        foreach ($crops as $i => $crop) {
            $idx = 'crops.' . $i;
            if (!is_array($crop)) {
                throw new ValidationException([$idx => ['Each crop must be an object with crop_id and qty']]);
            }

            $cropId = $crop['crop_id'] ?? null;
            $qty = $crop['qty'] ?? null;

            if (!is_numeric($cropId) || (int) $cropId <= 0) {
                throw new ValidationException([$idx . '.crop_id' => ['A valid crop_id is required']]);
            }
            if (!is_numeric($qty) || (float) $qty < 1) {
                throw new ValidationException([$idx . '.qty' => ['Quantity must be at least 1']]);
            }

            $cid = (int) $cropId;
            if (isset($seen[$cid])) {
                throw new ValidationException([$idx . '.crop_id' => ['Duplicate crop in booking']]);
            }
            $seen[$cid] = true;
        }
    }
}
