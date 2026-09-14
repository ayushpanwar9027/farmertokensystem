<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Token extends BaseModel
{
    protected string $table = 'tokens';
    protected bool $softDeletes = false;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_USED = 'USED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REVOKED = 'REVOKED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public function byBookingId(int $bookingId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM tokens WHERE booking_id = ? LIMIT 1",
            [$bookingId]
        ) ?: null;
    }

    public function activeForUser(int $userId): ?array
    {
        return Database::selectOne(
            "SELECT t.*, b.booking_number, b.date AS booking_date, b.slot_id,
                    c.id AS centre_id, c.name AS centre_name, c.code AS centre_code, s.start_time, s.end_time
             FROM tokens t
             INNER JOIN bookings b ON b.id = t.booking_id AND b.deleted_at IS NULL
             INNER JOIN procurement_centres c ON c.id = b.centre_id
             INNER JOIN slots s ON s.id = b.slot_id
             WHERE t.status = 'ACTIVE' AND b.user_id = ? AND b.deleted_at IS NULL
             ORDER BY t.issued_at DESC
             LIMIT 1",
            [$userId]
        ) ?: null;
    }

    public function byTokenNumber(string $tokenNumber): ?array
    {
        return Database::selectOne(
            "SELECT * FROM tokens WHERE token_number = ? LIMIT 1",
            [$tokenNumber]
        ) ?: null;
    }
}
