<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\BadRequestException;

class PaymentValidator
{
    private Validator $validator;

    public function __construct()
    {
        $this->validator = new Validator();
    }

    public function release(array $data): array
    {
        $method = $data['method'] ?? null;
        $reference = $data['payment_reference'] ?? null;

        if ($reference === null || trim((string) $reference) === '') {
            throw new BadRequestException('REFERENCE_REQUIRED', 'A payment reference is required to release a payment');
        }

        if (!in_array((string) $method, ['upi', 'bank_transfer', 'cash'], true)) {
            throw new BadRequestException('METHOD_INVALID', 'Invalid payment method');
        }

        if (mb_strlen((string) $reference) > 100) {
            throw new BadRequestException('VALIDATION_ERROR', 'Invalid release data', [
                'payment_reference' => 'must not exceed 100 characters',
            ]);
        }

        return [
            'method' => (string) $method,
            'payment_reference' => trim((string) $reference),
        ];
    }

    public function cancel(array $data): array
    {
        return $this->validator->validate($data, [
            'reason' => 'required|string|min:1|max:500',
        ]);
    }

    public function reverse(array $data): array
    {
        return $this->validator->validate($data, [
            'reason' => 'required|string|min:1|max:500',
        ]);
    }

    public function createRate(array $data): array
    {
        return $this->validator->validate($data, [
            'crop_id' => 'required|integer|min:1',
            'centre_id' => 'nullable|integer|min:0',
            'rate_per_kg' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date',
        ]);
    }

    public function updateRate(array $data): array
    {
        return $this->validator->validate($data, [
            'rate_per_kg' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'is_active' => 'nullable|integer|in:0,1',
        ]);
    }

    public function listFilters(array $data): array
    {
        $result = [];
        if (!empty($data['status'])) {
            $result['status'] = (string) $data['status'];
        }
        if (!empty($data['date'])) {
            $result['date'] = (string) $data['date'];
        }
        if (!empty($data['q'])) {
            $result['q'] = (string) $data['q'];
        }
        return $result;
    }
}
