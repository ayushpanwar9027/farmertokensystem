<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\NotFoundException;
use App\Models\Crop;

class BookingCropService
{
    private Crop $crops;

    public function __construct()
    {
        $this->crops = new Crop();
    }

    public function resolve(array $cropsData): array
    {
        $lines = [];

        foreach ($cropsData as $item) {
            $cropId = (int) ($item['crop_id'] ?? 0);
            $crop = $this->crops->find($cropId);

            if ($crop === null || !(int) $crop['is_active']) {
                throw new NotFoundException(
                    'CROP_NOT_FOUND',
                    'Crop not found or not available for booking',
                    404,
                    ['crop_id' => $cropId]
                );
            }

            $lines[] = [
                'crop_id' => (int) $crop['id'],
                'crop_name' => (string) $crop['name'],
                'crop_code' => (string) $crop['code'],
                'unit' => (string) $crop['unit'],
                'quantity_kg' => (float) $item['qty'],
            ];
        }

        return $lines;
    }

    public function totalQuantityKg(array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $total += $line['quantity_kg'];
        }
        return $total;
    }

    public function insertLines(int $bookingId, array $lines): void
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO booking_crops
                (booking_id, crop_id, crop_name, quantity_kg, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'PENDING', NOW(), NOW())"
        );

        foreach ($lines as $line) {
            $stmt->execute([
                $bookingId,
                $line['crop_id'],
                $line['crop_name'],
                $line['quantity_kg'],
            ]);
        }
    }

    public function cancelLines(int $bookingId): void
    {
        Database::getConnection()->prepare(
            "UPDATE booking_crops SET status = 'CANCELLED' WHERE booking_id = ?"
        )->execute([$bookingId]);
    }

    public function listForBooking(int $bookingId): array
    {
        return Database::select(
            "SELECT bc.id, bc.crop_id, bc.crop_name, bc.quantity_kg, bc.status
             FROM booking_crops bc
             WHERE bc.booking_id = ? AND bc.status <> 'CANCELLED'
             ORDER BY bc.id ASC",
            [$bookingId]
        );
    }
}
