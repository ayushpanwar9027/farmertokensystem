<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class CropRate extends BaseModel
{
    protected string $table = 'crop_rates';
    protected bool $softDeletes = false;

    public function byId(int $id): ?array
    {
        return $this->find($id);
    }

    public function activeForCropCentre(int $cropId, ?int $centreId, string $date): ?array
    {
        $params = [$cropId];

        if ($centreId !== null) {
            $row = Database::selectOne(
                "SELECT * FROM crop_rates
                 WHERE crop_id = ? AND centre_id = ? AND is_active = 1
                 AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
                 ORDER BY effective_from DESC, id DESC LIMIT 1",
                [$cropId, $centreId, $date, $date]
            );
            if ($row !== null) {
                return $row;
            }
        }

        return Database::selectOne(
            "SELECT * FROM crop_rates
             WHERE crop_id = ? AND centre_id IS NULL AND is_active = 1
             AND effective_from <= ? AND (effective_to IS NULL OR effective_to >= ?)
             ORDER BY effective_from DESC, id DESC LIMIT 1",
            [$cropId, $date, $date]
        );
    }

    public function allFiltered(array $filters = []): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['crop_id'])) {
            $where .= ' AND cr.crop_id = ?';
            $params[] = (int) $filters['crop_id'];
        }
        if (!empty($filters['centre_id'])) {
            $where .= ' AND cr.centre_id = ?';
            $params[] = (int) $filters['centre_id'];
        }
        if (isset($filters['is_active'])) {
            $where .= ' AND cr.is_active = ?';
            $params[] = (int) $filters['is_active'];
        }
        if (!empty($filters['date'])) {
            $where .= ' AND cr.effective_from <= ? AND (cr.effective_to IS NULL OR cr.effective_to >= ?)';
            $params[] = (string) $filters['date'];
            $params[] = (string) $filters['date'];
        }

        return Database::select(
            "SELECT cr.*, c.name AS crop_name, pc.name AS centre_name, pc.code AS centre_code
             FROM crop_rates cr
             INNER JOIN crops c ON c.id = cr.crop_id
             LEFT JOIN procurement_centres pc ON pc.id = cr.centre_id
             WHERE {$where}
             ORDER BY cr.effective_from DESC, cr.id DESC",
            $params
        );
    }
}
