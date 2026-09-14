<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\NotFoundException;
use App\Models\CropRate;

class RateService
{
    private CropRate $cropRates;
    private AuditService $audit;

    public function __construct()
    {
        $this->cropRates = new CropRate();
        $this->audit = new AuditService();
    }

    public function resolveRate(int $cropId, ?int $centreId, string $date): float
    {
        $rate = $this->cropRates->activeForCropCentre($cropId, $centreId, $date);
        if ($rate !== null) {
            return (float) $rate['rate_per_kg'];
        }

        $cropRow = Database::selectOne(
            "SELECT id FROM crops WHERE id = ? LIMIT 1",
            [$cropId]
        );
        if ($cropRow === null) {
            throw new NotFoundException('CROP_NOT_FOUND', 'Crop not found for rate resolution');
        }

        return 0.0;
    }

    public function resolveRateByName(string $cropName, ?int $centreId, string $date): float
    {
        $crop = Database::selectOne(
            "SELECT id FROM crops WHERE LOWER(name) = LOWER(?) LIMIT 1",
            [$cropName]
        );
        if ($crop === null) {
            return 0.0;
        }
        return $this->resolveRate((int) $crop['id'], $centreId, $date);
    }

    public function createRate(array $actor, array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $effectiveFrom = $data['effective_from'] ?? $now;

        $existingActive = null;
        if (!empty($data['centre_id'])) {
            $existingActive = Database::selectOne(
                "SELECT id FROM crop_rates
                 WHERE crop_id = ? AND centre_id = ? AND is_active = 1
                 AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                 LIMIT 1",
                [$data['crop_id'], $data['centre_id'], $effectiveFrom, $effectiveFrom]
            );
        } else {
            $existingActive = Database::selectOne(
                "SELECT id FROM crop_rates
                 WHERE crop_id = ? AND centre_id IS NULL AND is_active = 1
                 AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                 LIMIT 1",
                [$data['crop_id'], $effectiveFrom, $effectiveFrom]
            );
        }

        if ($existingActive !== null) {
            Database::update(
                'crop_rates',
                ['is_active' => 0, 'effective_to' => date('Y-m-d', strtotime($effectiveFrom . ' -1 day'))],
                'id = ?',
                [(int) $existingActive['id']]
            );
        }

        $id = Database::insert('crop_rates', [
            'crop_id' => (int) $data['crop_id'],
            'centre_id' => !empty($data['centre_id']) ? (int) $data['centre_id'] : null,
            'rate_per_kg' => round((float) $data['rate_per_kg'], 2),
            'effective_from' => $effectiveFrom,
            'effective_to' => !empty($data['effective_to']) ? $data['effective_to'] : null,
            'is_active' => 1,
            'created_by' => (int) $actor['id'],
        ]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'RATE_CREATE',
            'module' => 'PAYMENTS',
            'entity_type' => 'crop_rate',
            'entity_id' => $id,
            'new_value' => [
                'crop_id' => $data['crop_id'],
                'centre_id' => $data['centre_id'] ?? null,
                'rate_per_kg' => $data['rate_per_kg'],
                'effective_from' => $effectiveFrom,
            ],
        ]);

        return $this->present($this->cropRates->byId($id));
    }

    public function updateRate(array $actor, int $id, array $data): array
    {
        $rate = $this->cropRates->byId($id);
        if ($rate === null) {
            throw new NotFoundException('RATE_NOT_FOUND', 'Crop rate not found');
        }

        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (array_key_exists('rate_per_kg', $data)) {
            $updateData['rate_per_kg'] = round((float) $data['rate_per_kg'], 2);
        }
        if (array_key_exists('effective_from', $data) && $data['effective_from'] !== null) {
            $updateData['effective_from'] = $data['effective_from'];
        }
        if (array_key_exists('effective_to', $data)) {
            $updateData['effective_to'] = $data['effective_to'];
        }
        if (array_key_exists('is_active', $data)) {
            $updateData['is_active'] = (int) $data['is_active'];
        }

        Database::update('crop_rates', $updateData, 'id = ?', [$id]);

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => (string) ($actor['name'] ?? ''),
            'user_role' => (string) ($actor['role'] ?? ''),
            'action' => 'RATE_UPDATE',
            'module' => 'PAYMENTS',
            'entity_type' => 'crop_rate',
            'entity_id' => $id,
            'old_value' => ['rate_per_kg' => $rate['rate_per_kg']],
            'new_value' => $updateData,
        ]);

        return $this->present($this->cropRates->byId($id));
    }

    public function listRates(array $filters): array
    {
        $rows = $this->cropRates->allFiltered($filters);
        return [
            'count' => count($rows),
            'items' => array_map(fn($r) => $this->present($r), $rows),
        ];
    }

    public function getRatesForCrop(int $cropId, ?int $centreId = null, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $rate = $this->cropRates->activeForCropCentre($cropId, $centreId, $date);

        $crop = Database::selectOne("SELECT id, name FROM crops WHERE id = ?", [$cropId]);
        $cropName = $crop !== null ? $crop['name'] : 'Unknown';

        $centreName = null;
        $centreCode = null;
        if ($centreId !== null) {
            $centre = Database::selectOne("SELECT name, code FROM procurement_centres WHERE id = ?", [$centreId]);
            if ($centre !== null) {
                $centreName = $centre['name'];
                $centreCode = $centre['code'];
            }
        }

        return [
            'crop' => ['id' => $cropId, 'name' => $cropName],
            'centre' => $centreId !== null ? ['id' => $centreId, 'name' => $centreName, 'code' => $centreCode] : null,
            'date' => $date,
            'rate_per_kg' => $rate !== null ? (float) $rate['rate_per_kg'] : 0.0,
            'rate_source' => $rate !== null ? ($rate['centre_id'] !== null ? 'centre_override' : 'base') : 'none',
            'effective_from' => $rate !== null ? $rate['effective_from'] : null,
        ];
    }

    private function present(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'crop_id' => (int) $r['crop_id'],
            'crop_name' => $r['crop_name'] ?? null,
            'centre_id' => $r['centre_id'] !== null ? (int) $r['centre_id'] : null,
            'centre_name' => $r['centre_name'] ?? null,
            'centre_code' => $r['centre_code'] ?? null,
            'rate_per_kg' => (float) $r['rate_per_kg'],
            'effective_from' => $r['effective_from'],
            'effective_to' => $r['effective_to'] ?? null,
            'is_active' => (int) $r['is_active'] === 1,
            'created_by' => isset($r['created_by']) && $r['created_by'] !== null ? (int) $r['created_by'] : null,
            'created_at' => $r['created_at'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }
}
