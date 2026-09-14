<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Procurement extends BaseModel
{
    protected string $table = 'procurements';
    protected bool $softDeletes = true;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_REJECTED = 'REJECTED';

    public const TERMINAL_STATUSES = [self::STATUS_VERIFIED, self::STATUS_REJECTED];

    public function byId(int $id): ?array
    {
        return $this->find($id);
    }

    public function byNumber(string $number): ?array
    {
        return $this->findBy('procurement_number', $number);
    }

    public function countForBooking(int $bookingId): int
    {
        return $this->count(['booking_id' => $bookingId]);
    }

    public function allForBooking(int $bookingId): array
    {
        return Database::select(
            "SELECT * FROM procurements WHERE booking_id = ? AND deleted_at IS NULL ORDER BY id ASC",
            [$bookingId]
        );
    }

    public function allForUser(int $userId): array
    {
        return Database::select(
            "SELECT p.*, b.booking_number, c.name AS centre_name, c.code AS centre_code,
                    bc.quantity_kg AS booked_qty, bc.variety AS crop_variety
             FROM procurements p
             INNER JOIN bookings b ON b.id = p.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             LEFT JOIN booking_crops bc ON bc.id = p.booking_crop_id
             WHERE p.user_id = ? AND p.deleted_at IS NULL
             ORDER BY p.id DESC",
            [$userId]
        );
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
            $where .= ' AND (p.procurement_number LIKE ? OR p.crop_name LIKE ?)';
            $params[] = $q;
            $params[] = $q;
        }

        return Database::select(
            "SELECT p.*, b.booking_number, c.name AS centre_name, c.code AS centre_code,
                    bc.quantity_kg AS booked_qty
             FROM procurements p
             INNER JOIN bookings b ON b.id = p.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = p.centre_id
             LEFT JOIN booking_crops bc ON bc.id = p.booking_crop_id
             WHERE {$where}
             ORDER BY p.id DESC",
            $params
        );
    }
}
