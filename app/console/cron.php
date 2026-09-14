<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\QueuePositionService;
use App\Services\QueueService;
use App\Services\SlotGenerator;

$usage = "Usage: php app/console/cron.php --job=generate-slots\n";

$job = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--job=') === 0) {
        $job = substr($arg, strlen('--job='));
    }
}

if ($job === null || $job === '') {
    fwrite(STDERR, $usage);
    exit(1);
}

switch ($job) {
    case 'generate-slots':
        runGenerateSlots();
        break;
    case 'expire-pending-bookings':
        runExpirePendingBookings();
        break;
    case 'expire-unarrived-bookings':
        runExpireUnarrivedBookings();
        break;
    case 'queue-notify':
        runQueueNotify();
        break;
    case 'send-pending-notifications':
        runSendPendingNotifications();
        break;
    case 'retry-notifications':
        runRetryNotifications();
        break;
    default:
        fwrite(STDERR, "Unknown job: {$job}\n" . $usage);
        exit(1);
}

function runGenerateSlots(): void
{
    $generator = new SlotGenerator();
    $start = microtime(true);
    $summary = $generator->generate();
    $duration = round(microtime(true) - $start, 3);

    (new AuditService())->log([
        'action' => 'SLOTS_GENERATED',
        'module' => 'SLOTS',
        'entity_type' => 'slot',
        'new_value' => [
            'summary' => $summary,
            'duration_seconds' => $duration,
            'job' => 'generate-slots',
        ],
        'reason' => 'cron_generate_slots',
    ]);

    echo "generate-slots completed in {$duration}s: " . json_encode($summary) . "\n";
}

function runExpirePendingBookings(): void
{
    $start = microtime(true);
    $count = expireBookingsByStatus(
        'PENDING',
        "STR_TO_DATE(CONCAT(b.date, ' ', IFNULL(s.start_time, '23:59')), '%Y-%m-%d %H:%i') < NOW()"
    );

    $duration = round(microtime(true) - $start, 3);
    (new AuditService())->log([
        'action' => 'BOOKINGS_EXPIRED',
        'module' => 'BOOKINGS',
        'entity_type' => 'booking',
        'new_value' => ['count' => $count, 'reason' => 'pending_slot_passed', 'duration_seconds' => $duration, 'job' => 'expire-pending-bookings'],
        'reason' => 'cron_expire_pending_bookings',
    ]);
    echo "expire-pending-bookings completed in {$duration}s: {$count} expired\n";
}

function runExpireUnarrivedBookings(): void
{
    $start = microtime(true);
    $count = expireBookingsByStatus(
        'CONFIRMED',
        "STR_TO_DATE(CONCAT(b.date, ' ', IFNULL(s.start_time, '00:00')), '%Y-%m-%d %H:%i') < NOW()"
    );

    $duration = round(microtime(true) - $start, 3);
    (new AuditService())->log([
        'action' => 'BOOKINGS_EXPIRED',
        'module' => 'BOOKINGS',
        'entity_type' => 'booking',
        'new_value' => ['count' => $count, 'reason' => 'unarrived_by_slot_start', 'duration_seconds' => $duration, 'job' => 'expire-unarrived-bookings'],
        'reason' => 'cron_expire_unarrived_bookings',
    ]);
    echo "expire-unarrived-bookings completed in {$duration}s: {$count} expired\n";
}

function expireBookingsByStatus(string $status, string $expiryCondition): int
{
    $db = Database::getConnection();

    $ids = $db->prepare(
        "SELECT b.id, b.slot_id, b.user_id
         FROM bookings b
         INNER JOIN slots s ON s.id = b.slot_id
         WHERE b.status = ? AND b.deleted_at IS NULL AND {$expiryCondition}"
    );
    $ids->execute([$status]);
    $rows = $ids->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        return 0;
    }

    $idList = array_map(fn($r) => (int) $r['id'], $rows);
    $placeholders = implode(',', array_fill(0, count($idList), '?'));

    $db->prepare(
        "UPDATE bookings SET status = 'EXPIRED', updated_at = NOW()
         WHERE id IN ({$placeholders}) AND status = ? AND deleted_at IS NULL"
    )->execute(array_merge($idList, [$status]));

    foreach ($rows as $row) {
        $db->prepare(
            "UPDATE slots
             SET booked_count = IF(booked_count > 0, booked_count - 1, 0),
                 status = CASE WHEN status = 'FULL' AND IF(booked_count > 0, booked_count - 1, 0) < capacity THEN 'ACTIVE' ELSE status END,
                 updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        )->execute([(int) $row['slot_id']]);

        $db->prepare(
            "UPDATE tokens SET status = 'CANCELLED', revoked_reason = 'booking_expired', revoked_at = NOW()
             WHERE booking_id = ? AND status = 'ACTIVE'"
        )->execute([(int) $row['id']]);

        (new QueueService())->markBookingCancelled((int) $row['id']);
    }

    return count($rows);
}

function runQueueNotify(): void
{
    $start = microtime(true);
    $threshold = max(0, (int) get_setting('queue.notify_threshold', 3));
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $entries = Database::select(
        "SELECT qe.id, qe.centre_id, qe.date, qe.position, b.user_id, u.mobile, c.name AS centre_name
         FROM queue_entries qe
         INNER JOIN bookings b ON b.id = qe.booking_id AND b.deleted_at IS NULL
         INNER JOIN users u ON u.id = b.user_id
         INNER JOIN procurement_centres c ON c.id = qe.centre_id
         WHERE qe.date IN (?, ?) AND qe.status = 'WAITING' AND u.mobile IS NOT NULL AND u.mobile <> ''
         ORDER BY qe.centre_id, qe.date, qe.position ASC",
        [$today, $tomorrow]
    );

    $positions = new QueuePositionService();
    $notified = 0;
    $notifService = new NotificationService();

    foreach ($entries as $entry) {
        $ahead = $positions->aheadCount((int) $entry['centre_id'], (string) $entry['date'], (int) $entry['position']);
        if ($ahead >= $threshold) {
            continue;
        }

        try {
            $notifService->dispatch('queue_approaching', (int) $entry['user_id'], [
                'entity_id' => (int) $entry['id'],
                'entity_type' => 'queue_entry',
                'n' => (string) $ahead,
                'centre' => $entry['centre_name'] ?? '',
            ]);
            $notified++;
        } catch (\Throwable $ignored) {
        }
    }

    $duration = round(microtime(true) - $start, 3);
    (new AuditService())->log([
        'action' => 'QUEUE_NOTIFY',
        'module' => 'QUEUE',
        'entity_type' => 'queue_entry',
        'new_value' => [
            'notified' => $notified,
            'threshold' => $threshold,
            'dates' => [$today, $tomorrow],
            'duration_seconds' => $duration,
            'job' => 'queue-notify',
        ],
        'reason' => 'cron_queue_notify',
    ]);

    echo "queue-notify completed in {$duration}s: {$notified} approaching alerts queued\n";
}

function runSendPendingNotifications(): void
{
    $start = microtime(true);
    $service = new NotificationService();
    $sent = $service->retryPending();
    $duration = round(microtime(true) - $start, 3);

    (new AuditService())->log([
        'action' => 'SEND_PENDING_NOTIFICATIONS',
        'module' => 'NOTIFICATIONS',
        'entity_type' => 'notification',
        'new_value' => [
            'sent' => $sent,
            'duration_seconds' => $duration,
            'job' => 'send-pending-notifications',
        ],
        'reason' => 'cron_send_pending_notifications',
    ]);

    echo "send-pending-notifications completed in {$duration}s: {$sent} sent\n";
}

function runRetryNotifications(): void
{
    $start = microtime(true);
    $service = new NotificationService();
    $sent = $service->retryFailed();
    $duration = round(microtime(true) - $start, 3);

    (new AuditService())->log([
        'action' => 'RETRY_NOTIFICATIONS',
        'module' => 'NOTIFICATIONS',
        'entity_type' => 'notification',
        'new_value' => [
            'sent' => $sent,
            'duration_seconds' => $duration,
            'job' => 'retry-notifications',
        ],
        'reason' => 'cron_retry_notifications',
    ]);

    echo "retry-notifications completed in {$duration}s: {$sent} sent\n";
}
