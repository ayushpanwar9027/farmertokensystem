<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class Booking extends BaseModel
{
    protected string $table = 'bookings';
    protected bool $softDeletes = true;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_EXPIRED = 'EXPIRED';

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    public function byBookingNumber(string $bookingNumber): ?array
    {
        return Database::selectOne(
            "SELECT * FROM bookings WHERE booking_number = ? AND deleted_at IS NULL LIMIT 1",
            [$bookingNumber]
        ) ?: null;
    }

    public function countActiveForUser(int $userId): int
    {
        $row = Database::selectOne(
            "SELECT COUNT(*) AS total FROM bookings
             WHERE user_id = ? AND deleted_at IS NULL
               AND status IN ('PENDING','CONFIRMED')",
            [$userId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function nextBookingSequence(string $date): int
    {
        // Atomic per-day sequence: first call seeds the row (seq=1), every
        // concurrent call then bumps it via LAST_INSERT_ID(seq + 1) which is
        // serialized by the primary-key row lock. Without this, concurrent
        // requests would each read COUNT(*) and collide on booking_number.
        Database::query(
            "INSERT INTO booking_sequences (date_key, seq) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)",
            [$date]
        );
        $row = Database::selectOne("SELECT LAST_INSERT_ID() AS seq");
        return (int) ($row['seq'] ?? 1);
    }

    public function crops(int $bookingId): array
    {
        return Database::select(
            "SELECT bc.*, c.code AS crop_code, c.name AS catalog_name, c.name_hi AS catalog_name_hi, c.unit
             FROM booking_crops bc
             LEFT JOIN crops c ON c.id = bc.crop_id
             WHERE bc.booking_id = ?",
            [$bookingId]
        );
    }

    public function token(int $bookingId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM tokens WHERE booking_id = ?",
            [$bookingId]
        ) ?: null;
    }
}
