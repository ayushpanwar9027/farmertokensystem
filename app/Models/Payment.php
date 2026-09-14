<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Payment extends BaseModel
{
    protected string $table = 'payments';
    protected bool $softDeletes = true;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_INITIATED = 'INITIATED';
    public const STATUS_RELEASED = 'RELEASED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_REVERSED = 'REVERSED';

    public const ELIGIBLE_FOR_RELEASE = [self::STATUS_PENDING, self::STATUS_INITIATED];
    public const ELIGIBLE_FOR_CANCEL = [self::STATUS_PENDING, self::STATUS_INITIATED];

    public function byId(int $id): ?array
    {
        return $this->find($id);
    }

    public function byProcurementId(int $procurementId): ?array
    {
        return $this->findBy('procurement_id', $procurementId);
    }

    public function allForUserScoped(int $userId, array $filters = []): array
    {
        $where = 'p.user_id = ? AND p.deleted_at IS NULL';
        $params = [$userId];

        if (!empty($filters['status'])) {
            $where .= ' AND p.status = ?';
            $params[] = (string) $filters['status'];
        }

        if (!empty($filters['date'])) {
            $where .= ' AND DATE(p.created_at) = ?';
            $params[] = (string) $filters['date'];
        }

        if (!empty($filters['q'])) {
            $q = '%' . (string) $filters['q'] . '%';
            $where .= ' AND (p.crop_name LIKE ? OR p.payment_reference LIKE ?)';
            $params[] = $q;
            $params[] = $q;
        }

        return Database::select(
            "SELECT p.*, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    c.name AS centre_name, c.code AS centre_code,
                    pr.crop_name AS proc_crop_name, pr.accepted_weight, pr.quality_grade
             FROM payments p
             INNER JOIN users u ON u.id = p.user_id
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             INNER JOIN procurements pr ON pr.id = p.procurement_id
             WHERE {$where}
             ORDER BY p.id DESC",
            $params
        );
    }
}
