<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\QueueEntry;
use App\Models\Token;
use App\Validators\QueueValidator;

class QueueService
{
    private const CALL_WAVE_GRACE_SECONDS = 2;
    private const AUTO_COMPLETE_MINUTES = 60;

    private QueuePositionService $positions;
    private QueueValidator $validator;
    private SettingService $settings;
    private AuditService $audit;
    private ScopeService $scope;
    private Token $tokens;

    public function __construct()
    {
        $this->positions = new QueuePositionService();
        $this->validator = new QueueValidator();
        $this->settings = new SettingService();
        $this->audit = new AuditService();
        $this->scope = new ScopeService();
        $this->tokens = new Token();
    }

    public function enqueueFromBooking(int $bookingId, int $centreId, string $date): int
    {
        $existing = Database::selectOne(
            "SELECT id FROM queue_entries WHERE booking_id = ? LIMIT 1",
            [$bookingId]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $position = $this->positions->nextPosition($centreId, $date);

        return (int) Database::insert('queue_entries', [
            'booking_id' => $bookingId,
            'centre_id' => $centreId,
            'date' => $date,
            'status' => QueueEntry::STATUS_WAITING,
            'position' => $position,
        ]);
    }

    public function callNext(array $actor, array $data): array
    {
        $data = $this->validator->callNext($data);
        $centreId = (int) $data['centre_id'];
        $date = (string) ($data['date'] ?? date('Y-m-d'));

        $this->scope->assertCentreScope($actor, $centreId);

        $lockKey = 'fps_queue_call_' . $centreId . '_' . $date;
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT GET_LOCK(?, 5)");
        $stmt->execute([$lockKey]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new ConflictException('QUEUE_BUSY', 'Queue is busy, please retry');
        }

        $inTransaction = false;
        try {
            Database::beginTransaction();
            $inTransaction = true;

            $waveCutoff = date('Y-m-d H:i:s', time() - self::CALL_WAVE_GRACE_SECONDS);
            $recent = Database::selectOne(
                "SELECT id FROM queue_entries
                 WHERE centre_id = ? AND date = ? AND status = ? AND called_at >= ?
                 ORDER BY id DESC LIMIT 1",
                [$centreId, $date, QueueEntry::STATUS_CALLED, $waveCutoff]
            );
            if ($recent !== null) {
                throw new ConflictException(
                    'CONCURRENT_UPDATE',
                    'Another farmer was just called, please refresh and retry'
                );
            }

            $this->autoCompleteStale($centreId, $date);

            $entry = Database::selectOne(
                "SELECT * FROM queue_entries
                 WHERE centre_id = ? AND date = ? AND status = ?
                 ORDER BY position ASC, id ASC LIMIT 1",
                [$centreId, $date, QueueEntry::STATUS_WAITING]
            );
            if ($entry === null) {
                throw new ConflictException('QUEUE_EMPTY', 'No farmers are waiting in the queue');
            }

            $now = date('Y-m-d H:i:s');
            $affected = Database::update(
                'queue_entries',
                [
                    'status' => QueueEntry::STATUS_CALLED,
                    'called_at' => $now,
                    'called_by' => (int) $actor['id'],
                ],
                'id = ? AND status = ?',
                [(int) $entry['id'], QueueEntry::STATUS_WAITING]
            );
            if ($affected !== 1) {
                throw new ConflictException('CONCURRENT_UPDATE', 'Another operator just called this token, please retry');
            }

            $tokenRow = Database::selectOne(
                "SELECT token_number FROM tokens WHERE booking_id = ? LIMIT 1",
                [(int) $entry['booking_id']]
            );
            $tokenNumber = $tokenRow['token_number'] ?? null;

            $ahead = $this->positions->aheadCount($centreId, $date, (int) $entry['position']);
            $expectedWait = $ahead * $this->avgMinutesPerToken();

            $this->notifyForEntry((int) $entry['id'], 'QUEUE_CALLED', 'queue_called_' . (int) $entry['id']);
            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => (string) ($actor['name'] ?? ''),
                'user_role' => (string) ($actor['role'] ?? ''),
                'action' => 'QUEUE_CALL_NEXT',
                'module' => 'QUEUE',
                'entity_type' => 'queue_entry',
                'entity_id' => (int) $entry['id'],
                'new_value' => [
                    'centre_id' => $centreId,
                    'date' => $date,
                    'booking_id' => (int) $entry['booking_id'],
                    'token' => $tokenNumber,
                ],
            ]);

            Database::commit();
            $inTransaction = false;

            return [
                'entry_id' => (int) $entry['id'],
                'token' => $tokenNumber,
                'booking_id' => (int) $entry['booking_id'],
                'status' => QueueEntry::STATUS_CALLED,
                'position' => (int) $entry['position'],
                'farmers_ahead' => $ahead,
                'expected_wait_minutes' => $expectedWait,
                'called_at' => $now,
            ];
        } catch (\Throwable $e) {
            if ($inTransaction) {
                try {
                    Database::rollback();
                } catch (\Throwable $ignored) {
                }
            }
            throw $e;
        } finally {
            try {
                $release = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                $release->execute([$lockKey]);
            } catch (\Throwable $ignored) {
            }
        }
    }

    public function skip(array $actor, int $entryId, array $data): array
    {
        $this->validator->skip($data);
        $entry = $this->requireEntry($entryId);
        $this->scope->assertCentreScope($actor, (int) $entry['centre_id']);

        if (!in_array((string) $entry['status'], [QueueEntry::STATUS_CALLED, QueueEntry::STATUS_IN_PROGRESS], true)) {
            throw new ConflictException('ENTRY_STATUS_INVALID', 'Only called or in-progress entries can be skipped');
        }

        $reason = (string) ($data['reason'] ?? '');
        $recallLimit = max(1, $this->settings->getInt('queue.recall_limit', 1));
        $recallEligible = (int) $entry['recall_count'] < $recallLimit ? 1 : 0;

        Database::beginTransaction();
        try {
            Database::update(
                'queue_entries',
                [
                    'status' => QueueEntry::STATUS_SKIPPED,
                    'skipped_at' => date('Y-m-d H:i:s'),
                    'skipped_by' => (int) $actor['id'],
                    'skip_reason' => $reason,
                    'recall_eligible' => $recallEligible,
                ],
                'id = ?',
                [(int) $entry['id']]
            );
            $this->positions->renumber((int) $entry['centre_id'], (string) $entry['date']);
            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => (string) ($actor['name'] ?? ''),
                'user_role' => (string) ($actor['role'] ?? ''),
                'action' => 'QUEUE_SKIP',
                'module' => 'QUEUE',
                'entity_type' => 'queue_entry',
                'entity_id' => (int) $entry['id'],
                'new_value' => ['reason' => $reason, 'recall_eligible' => (bool) $recallEligible],
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->entrySummary((int) $entry['id']);
    }

    public function noShow(array $actor, int $entryId): array
    {
        $entry = $this->requireEntry($entryId);
        $this->scope->assertCentreScope($actor, (int) $entry['centre_id']);

        if ((string) $entry['status'] !== QueueEntry::STATUS_CALLED) {
            throw new ConflictException('ENTRY_STATUS_INVALID', 'Only called entries can be marked as no-show');
        }

        $graceMinutes = max(0, $this->settings->getInt('queue.grace_no_show_minutes', 5));
        $graceUntil = strtotime((string) $entry['called_at']) + ($graceMinutes * 60);
        if (time() < $graceUntil) {
            throw new ConflictException(
                'ENTRY_STATUS_INVALID',
                'No-show can be marked only after the grace period of ' . $graceMinutes . ' minute(s)',
                ['graced_until' => date('Y-m-d H:i:s', $graceUntil)]
            );
        }

        Database::beginTransaction();
        try {
            Database::update(
                'queue_entries',
                [
                    'status' => QueueEntry::STATUS_NO_SHOW,
                    'no_show_at' => date('Y-m-d H:i:s'),
                    'no_show_by' => (int) $actor['id'],
                ],
                'id = ?',
                [(int) $entry['id']]
            );
            $this->positions->renumber((int) $entry['centre_id'], (string) $entry['date']);
            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => (string) ($actor['name'] ?? ''),
                'user_role' => (string) ($actor['role'] ?? ''),
                'action' => 'QUEUE_NO_SHOW',
                'module' => 'QUEUE',
                'entity_type' => 'queue_entry',
                'entity_id' => (int) $entry['id'],
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->entrySummary((int) $entry['id']);
    }

    public function recall(array $actor, int $entryId): array
    {
        $entry = $this->requireEntry($entryId);
        $this->scope->assertCentreScope($actor, (int) $entry['centre_id']);

        if ((string) $entry['status'] !== QueueEntry::STATUS_SKIPPED) {
            throw new ConflictException('ENTRY_STATUS_INVALID', 'Only skipped entries can be recalled');
        }

        $recallLimit = max(1, $this->settings->getInt('queue.recall_limit', 1));
        if ((int) $entry['recall_count'] >= $recallLimit) {
            throw new ConflictException('RECALL_LIMIT_REACHED', 'This entry can no longer be recalled');
        }

        $now = date('Y-m-d H:i:s');
        $position = $this->positions->nextPosition((int) $entry['centre_id'], (string) $entry['date']);

        Database::beginTransaction();
        try {
            Database::update(
                'queue_entries',
                [
                    'status' => QueueEntry::STATUS_CALLED,
                    'position' => $position,
                    'called_at' => $now,
                    'called_by' => (int) $actor['id'],
                    'recall_count' => (int) $entry['recall_count'] + 1,
                    'recall_eligible' => 0,
                    'recalled_at' => $now,
                ],
                'id = ?',
                [(int) $entry['id']]
            );
            $this->notifyForEntry((int) $entry['id'], 'QUEUE_CALLED', 'queue_called_' . (int) $entry['id']);
            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => (string) ($actor['name'] ?? ''),
                'user_role' => (string) ($actor['role'] ?? ''),
                'action' => 'QUEUE_RECALL',
                'module' => 'QUEUE',
                'entity_type' => 'queue_entry',
                'entity_id' => (int) $entry['id'],
                'new_value' => ['recall_count' => (int) $entry['recall_count'] + 1, 'position' => $position],
            ]);
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        return $this->entrySummary((int) $entry['id']);
    }

    public function live(int $centreId, string $date): array
    {
        $counts = $this->statusCounts($centreId, $date);

        $waiting = (int) $counts[QueueEntry::STATUS_WAITING];
        $called = (int) $counts[QueueEntry::STATUS_CALLED];
        $inProgress = (int) $counts[QueueEntry::STATUS_IN_PROGRESS];
        $queueSize = $waiting + $called + $inProgress;

        $current = $this->currentServiceEntry($centreId, $date);

        $lastCalled = Database::selectOne(
            "SELECT qe.called_at, t.token_number
             FROM queue_entries qe
             LEFT JOIN tokens t ON t.booking_id = qe.booking_id
             WHERE qe.centre_id = ? AND qe.date = ? AND qe.called_at IS NOT NULL
             ORDER BY qe.called_at DESC LIMIT 1",
            [$centreId, $date]
        );

        return [
            'centre_id' => $centreId,
            'date' => $date,
            'queue_size' => $queueSize,
            'waiting' => $waiting,
            'called' => $called,
            'in_progress' => $inProgress,
            'completed' => (int) $counts[QueueEntry::STATUS_COMPLETED],
            'skipped' => (int) $counts[QueueEntry::STATUS_SKIPPED],
            'no_show' => (int) $counts[QueueEntry::STATUS_NO_SHOW],
            'cancelled' => (int) $counts[QueueEntry::STATUS_CANCELLED],
            'current' => $current,
            'last_called_token' => $lastCalled['token_number'] ?? null,
            'last_called_at' => $lastCalled['called_at'] ?? null,
            'avg_wait_minutes' => $this->averageWaitMinutes($centreId, $date),
            'eta_minutes' => $waiting * $this->avgMinutesPerToken(),
        ];
    }

    public function myEntry(array $actor): array
    {
        $token = $this->tokens->activeForUser((int) $actor['id']);
        if ($token === null) {
            throw new NotFoundException('QUEUE_NOT_FOUND', 'No active queue entry found');
        }

        $entry = Database::selectOne(
            "SELECT * FROM queue_entries WHERE booking_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $token['booking_id']]
        );
        if ($entry === null) {
            throw new NotFoundException('QUEUE_NOT_FOUND', 'No active queue entry found');
        }

        $centreId = (int) $entry['centre_id'];
        $date = (string) $entry['date'];
        $ahead = $this->positions->aheadCount($centreId, $date, (int) $entry['position']);

        return [
            'token' => (string) $token['token_number'],
            'date' => $date,
            'position' => (int) $entry['position'],
            'ahead' => $ahead,
            'status' => (string) $entry['status'],
            'waited_minutes' => $this->waitedMinutes($entry),
            'eta_minutes' => $ahead * $this->avgMinutesPerToken(),
            'centre' => [
                'id' => (int) ($token['centre_id'] ?? 0),
                'name' => (string) ($token['centre_name'] ?? ''),
                'code' => (string) ($token['centre_code'] ?? ''),
            ],
        ];
    }

    public function statusByToken(array $actor, string $bookingToken): array
    {
        $token = $this->tokens->byTokenNumber($bookingToken);
        if ($token === null) {
            throw new NotFoundException('QUEUE_NOT_FOUND', 'Queue not found for this token');
        }

        $booking = Database::selectOne(
            "SELECT user_id FROM bookings WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [(int) $token['booking_id']]
        );
        if ($booking === null || (int) $booking['user_id'] !== (int) $actor['id']) {
            throw new NotFoundException('QUEUE_NOT_FOUND', 'Queue not found for this token');
        }

        $entry = Database::selectOne(
            "SELECT * FROM queue_entries WHERE booking_id = ? ORDER BY id DESC LIMIT 1",
            [(int) $token['booking_id']]
        );
        if ($entry === null) {
            throw new NotFoundException('QUEUE_NOT_FOUND', 'Queue not found for this token');
        }

        $ahead = $this->positions->aheadCount((int) $entry['centre_id'], (string) $entry['date'], (int) $entry['position']);

        return [
            'token' => (string) $token['token_number'],
            'status' => (string) $entry['status'],
            'position' => (int) $entry['position'],
            'ahead' => $ahead,
            'waited_minutes' => $this->waitedMinutes($entry),
            'eta_minutes' => $ahead * $this->avgMinutesPerToken(),
            'called_at' => $entry['called_at'] ?? null,
        ];
    }

    public function operatorList(array $actor, array $filters): array
    {
        $filters = $this->validator->listFilters($filters);
        $centreId = (int) $filters['centre_id'];
        $date = (string) ($filters['date'] ?? date('Y-m-d'));

        $this->scope->assertCentreScope($actor, $centreId);

        $where = 'qe.centre_id = ? AND qe.date = ?';
        $params = [$centreId, $date];
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            $where .= ' AND qe.status = ?';
            $params[] = $status;
        }

        $rows = Database::select(
            "SELECT qe.*, u.id AS farmer_user_id, u.name AS farmer_name, u.mobile AS farmer_mobile,
                    t.token_number, b.booking_number
             FROM queue_entries qe
             INNER JOIN bookings b ON b.id = qe.booking_id AND b.deleted_at IS NULL
             INNER JOIN users u ON u.id = b.user_id
             LEFT JOIN tokens t ON t.booking_id = qe.booking_id
             WHERE {$where}
             ORDER BY qe.position ASC, qe.id ASC",
            $params
        );

        $avg = $this->avgMinutesPerToken();
        $entries = [];
        foreach ($rows as $row) {
            $ahead = $this->positions->aheadCount($centreId, $date, (int) $row['position']);
            $entries[] = [
                'id' => (int) $row['id'],
                'token' => $row['token_number'] ?? null,
                'booking_number' => (string) $row['booking_number'],
                'position' => (int) $row['position'],
                'status' => (string) $row['status'],
                'farmers_ahead' => $ahead,
                'eta_minutes' => $ahead * $avg,
                'farmer' => [
                    'id' => (int) $row['farmer_user_id'],
                    'name' => (string) $row['farmer_name'],
                    'mobile' => (string) $row['farmer_mobile'],
                ],
                'called_at' => $row['called_at'] ?? null,
                'skip_reason' => $row['skip_reason'] ?? null,
            ];
        }

        return [
            'centre_id' => $centreId,
            'date' => $date,
            'count' => count($entries),
            'entries' => $entries,
        ];
    }

    public function stats(array $actor, array $query): array
    {
        $query = $this->validator->stats($query);
        $centreId = (int) $query['centre_id'];
        $date = (string) ($query['date'] ?? date('Y-m-d'));

        $this->scope->assertCentreScope($actor, $centreId);

        $counts = $this->statusCounts($centreId, $date);
        $completed = (int) $counts[QueueEntry::STATUS_COMPLETED];
        $processed = 0;
        foreach ([QueueEntry::STATUS_COMPLETED, QueueEntry::STATUS_SKIPPED, QueueEntry::STATUS_NO_SHOW, QueueEntry::STATUS_CANCELLED] as $status) {
            $processed += (int) $counts[$status];
        }

        return [
            'centre_id' => $centreId,
            'date' => $date,
            'served_today' => $completed,
            'waiting' => (int) $counts[QueueEntry::STATUS_WAITING],
            'called' => (int) $counts[QueueEntry::STATUS_CALLED],
            'in_progress' => (int) $counts[QueueEntry::STATUS_IN_PROGRESS],
            'skipped' => (int) $counts[QueueEntry::STATUS_SKIPPED],
            'no_show' => (int) $counts[QueueEntry::STATUS_NO_SHOW],
            'cancelled' => (int) $counts[QueueEntry::STATUS_CANCELLED],
            'avg_wait_minutes' => $this->averageWaitMinutes($centreId, $date),
            'avg_procurement_minutes' => $this->avgMinutesPerToken(),
            'completion_rate' => $processed > 0 ? round($completed / $processed, 2) : 0.0,
        ];
    }

    public function markBookingCancelled(int $bookingId): void
    {
        $entry = Database::selectOne(
            "SELECT id, centre_id, date, status FROM queue_entries WHERE booking_id = ? LIMIT 1",
            [$bookingId]
        );
        if ($entry === null) {
            return;
        }

        if (!in_array((string) $entry['status'], QueueEntry::ACTIVE_STATUSES, true)) {
            return;
        }

        Database::update(
            'queue_entries',
            [
                'status' => QueueEntry::STATUS_CANCELLED,
                'cancelled_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [(int) $entry['id']]
        );

        $this->positions->renumber((int) $entry['centre_id'], (string) $entry['date']);
    }

    private function autoCompleteStale(int $centreId, string $date): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::AUTO_COMPLETE_MINUTES * 60);
        $rows = Database::select(
            "SELECT id FROM queue_entries
             WHERE centre_id = ? AND date = ? AND status = ? AND started_at < ?",
            [$centreId, $date, QueueEntry::STATUS_IN_PROGRESS, $cutoff]
        );

        $completed = false;
        foreach ($rows as $row) {
            $affected = Database::update(
                'queue_entries',
                ['status' => QueueEntry::STATUS_COMPLETED, 'completed_at' => date('Y-m-d H:i:s')],
                'id = ? AND status = ?',
                [(int) $row['id'], QueueEntry::STATUS_IN_PROGRESS]
            );
            if ($affected === 1) {
                $completed = true;
            }
        }

        if ($completed) {
            $this->positions->renumber($centreId, $date);
        }
    }

    private function notifyForEntry(int $entryId, string $eventType, string $eventRef): void
    {
        $row = Database::selectOne(
            "SELECT u.id AS user_id, u.mobile, qe.centre_id, qe.position,
                    t.token_number, c.name AS centre_name
             FROM queue_entries qe
             INNER JOIN bookings b ON b.id = qe.booking_id AND b.deleted_at IS NULL
             INNER JOIN users u ON u.id = b.user_id
             INNER JOIN procurement_centres c ON c.id = qe.centre_id
             LEFT JOIN tokens t ON t.booking_id = qe.booking_id
             WHERE qe.id = ?",
            [$entryId]
        );
        if ($row === null || empty($row['mobile'])) {
            return;
        }

        $notificationEvent = match ($eventType) {
            'QUEUE_CALLED' => 'farmer_called',
            'QUEUE_APPROACHING' => 'queue_approaching',
            default => 'farmer_called',
        };

        $params = [
            'entity_id' => $entryId,
            'token' => $row['token_number'] ?? '',
            'centre' => $row['centre_name'] ?? '',
            'n' => (string) ($row['position'] ?? ''),
        ];

        try {
            (new NotificationService())->dispatch($notificationEvent, (int) $row['user_id'], $params);
        } catch (\Throwable $ignored) {
        }
    }

    private function requireEntry(int $entryId): array
    {
        $entry = Database::selectOne(
            "SELECT * FROM queue_entries WHERE id = ? LIMIT 1",
            [$entryId]
        );
        if ($entry === null) {
            throw new NotFoundException('QUEUE_ENTRY_NOT_FOUND', 'Queue entry not found');
        }

        return $entry;
    }

    private function entrySummary(int $entryId): array
    {
        $entry = $this->requireEntry($entryId);
        $ahead = $this->positions->aheadCount((int) $entry['centre_id'], (string) $entry['date'], (int) $entry['position']);
        $tokenRow = Database::selectOne(
            "SELECT token_number FROM tokens WHERE booking_id = ? LIMIT 1",
            [(int) $entry['booking_id']]
        );

        return [
            'entry_id' => (int) $entry['id'],
            'token' => $tokenRow['token_number'] ?? null,
            'booking_id' => (int) $entry['booking_id'],
            'position' => (int) $entry['position'],
            'status' => (string) $entry['status'],
            'farmers_ahead' => $ahead,
            'eta_minutes' => $ahead * $this->avgMinutesPerToken(),
            'called_at' => $entry['called_at'] ?? null,
            'skip_reason' => $entry['skip_reason'] ?? null,
            'recall_eligible' => (bool) $entry['recall_eligible'],
            'recall_count' => (int) $entry['recall_count'],
        ];
    }

    private function currentServiceEntry(int $centreId, string $date): ?array
    {
        $inProgress = Database::selectOne(
            "SELECT qe.id, qe.status, qe.started_at, t.token_number
             FROM queue_entries qe
             LEFT JOIN tokens t ON t.booking_id = qe.booking_id
             WHERE qe.centre_id = ? AND qe.date = ? AND qe.status = ?
             ORDER BY qe.started_at DESC LIMIT 1",
            [$centreId, $date, QueueEntry::STATUS_IN_PROGRESS]
        );
        if ($inProgress !== null) {
            return [
                'entry_id' => (int) $inProgress['id'],
                'token' => $inProgress['token_number'] ?? null,
                'status' => QueueEntry::STATUS_IN_PROGRESS,
            ];
        }

        $latestCalled = Database::selectOne(
            "SELECT qe.id, qe.called_at, t.token_number
             FROM queue_entries qe
             LEFT JOIN tokens t ON t.booking_id = qe.booking_id
             WHERE qe.centre_id = ? AND qe.date = ? AND qe.status = ?
             ORDER BY qe.called_at DESC LIMIT 1",
            [$centreId, $date, QueueEntry::STATUS_CALLED]
        );
        if ($latestCalled !== null) {
            return [
                'entry_id' => (int) $latestCalled['id'],
                'token' => $latestCalled['token_number'] ?? null,
                'status' => QueueEntry::STATUS_CALLED,
            ];
        }

        return null;
    }

    private function statusCounts(int $centreId, string $date): array
    {
        $rows = Database::select(
            "SELECT status, COUNT(*) AS total FROM queue_entries
             WHERE centre_id = ? AND date = ? GROUP BY status",
            [$centreId, $date]
        );

        $counts = [
            QueueEntry::STATUS_WAITING => 0,
            QueueEntry::STATUS_CALLED => 0,
            QueueEntry::STATUS_IN_PROGRESS => 0,
            QueueEntry::STATUS_COMPLETED => 0,
            QueueEntry::STATUS_SKIPPED => 0,
            QueueEntry::STATUS_NO_SHOW => 0,
            QueueEntry::STATUS_CANCELLED => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }

        return $counts;
    }

    private function averageWaitMinutes(int $centreId, string $date): int
    {
        $row = Database::selectOne(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, qe.called_at, qe.completed_at)) AS avg_wait
             FROM (
                 SELECT called_at, completed_at
                 FROM queue_entries
                 WHERE centre_id = ? AND date = ? AND status = 'COMPLETED'
                   AND called_at IS NOT NULL AND completed_at IS NOT NULL
                 ORDER BY completed_at DESC
                 LIMIT 10
             ) qe",
            [$centreId, $date]
        );

        $avg = (float) ($row['avg_wait'] ?? 0);

        if ($avg > 0) {
            return max(1, (int) round($avg));
        }

        return $this->avgMinutesPerToken();
    }

    private function avgMinutesPerToken(): int
    {
        return max(1, $this->settings->getInt('queue.avg_minutes_per_token', 10));
    }

    private function waitedMinutes(array $entry): int
    {
        $reference = null;
        $status = (string) $entry['status'];

        if ($status === QueueEntry::STATUS_WAITING && !empty($entry['created_at'])) {
            $reference = strtotime((string) $entry['created_at']);
        } elseif (in_array($status, [QueueEntry::STATUS_CALLED, QueueEntry::STATUS_IN_PROGRESS], true) && !empty($entry['called_at'])) {
            $reference = strtotime((string) $entry['called_at']);
        }

        if ($reference === null) {
            return 0;
        }

        return max(0, (int) floor((time() - $reference) / 60));
    }
}