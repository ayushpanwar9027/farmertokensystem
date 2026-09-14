<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\NotFoundException;
use App\Models\NotificationLog;
use App\Services\NotificationService;
use App\Services\OneSignalService;
use App\Services\RbacService;

class NotificationsController
{
    public function index(Request $request): void
    {
        $actor = $this->requireActor($request);
        $userId = (int) $actor['id'];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?",
            [$userId]
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));

        $rows = Database::select(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        Response::success($rows, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    public function show(Request $request): void
    {
        $actor = $this->requireActor($request);
        $id = (int) $request->getParam('id');
        $userId = (int) $actor['id'];

        $row = Database::selectOne(
            "SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1",
            [$id, $userId]
        );

        if ($row === null) {
            throw new NotFoundException('NOTIFICATION_NOT_FOUND', 'Notification not found');
        }

        Response::success(['notification' => $row]);
    }

    public function markRead(Request $request): void
    {
        $actor = $this->requireActor($request);
        $id = (int) $request->getParam('id');
        $userId = (int) $actor['id'];

        Database::update(
            'notifications',
            ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
            'id = ? AND user_id = ? AND is_read = 0',
            [$id, $userId]
        );

        Response::success(['message' => 'Notification marked as read']);
    }

    public function markAllRead(Request $request): void
    {
        $actor = $this->requireActor($request);
        $userId = (int) $actor['id'];

        Database::getConnection()->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0"
        )->execute([$userId]);

        Response::success(['message' => 'All notifications marked as read']);
    }

    public function adminIndex(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->requirePermission($actor, 'notifications.view');

        $model = new NotificationLog();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $filters = $request->only(['status', 'event', 'date_from', 'date_to', 'q']);
        $result = $model->adminList($filters, $page, $perPage);

        Response::success($result['data'], ['pagination' => $result['pagination']]);
    }

    public function adminSummary(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->requirePermission($actor, 'notifications.view');

        $model = new NotificationLog();
        $summary = $model->todaySummary();

        Response::success(['summary' => $summary]);
    }

    public function testPush(Request $request): void
    {
        $actor = $this->requireActor($request);
        $this->requirePermission($actor, 'notifications.test');

        $playerId = trim((string) $request->input('onesignal_player_id', ''));
        $targetAll = (bool) $request->input('all', false);

        $oneSignal = new OneSignalService();
        if (!$oneSignal->isConfigured()) {
            $message = 'OneSignal not configured — test push logged (dev fallback)';
            $logPath = storage_path('logs/notification.log');
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            file_put_contents(
                $logPath,
                json_encode([
                    'timestamp' => gmdate('c'),
                    'level' => 'INFO',
                    'service' => 'TestPush',
                    'message' => $message,
                    'actor_id' => (int) $actor['id'],
                    'target_player_id' => $playerId !== '' ? $this->maskPlayerId($playerId) : 'all',
                ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );

            (new \App\Services\AuditService())->log([
                'user_id' => (int) $actor['id'],
                'action' => 'TEST_PUSH',
                'module' => 'NOTIFICATIONS',
                'entity_type' => 'notification',
                'new_value' => ['target' => $targetAll ? 'all' : $this->maskPlayerId($playerId), 'status' => 'DEV_LOG_FALLBACK'],
            ]);

            Response::success(['message' => $message]);
            return;
        }

        if (!$targetAll && ($playerId === '' || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $playerId))) {
            Response::error('INVALID_RECIPIENT', 'Invalid OneSignal player ID format', 400);
            return;
        }

        $playerIds = [];
        if ($targetAll) {
            $rows = Database::select(
                "SELECT DISTINCT onesignal_player_id FROM user_devices
                 WHERE onesignal_player_id IS NOT NULL AND onesignal_player_id <> ''
                 LIMIT 1000"
            );
            $playerIds = array_column($rows, 'onesignal_player_id');
        } else {
            $playerIds = [$playerId];
        }

        if (empty($playerIds)) {
            Response::error('NO_DEVICES', 'No registered devices found', 404);
            return;
        }

        $result = $oneSignal->sendPush(
            $playerIds,
            ['en' => 'Test Notification', 'hi' => 'परीक्षण सूचना'],
            ['en' => 'This is a test push from the admin panel.', 'hi' => 'यह एडमिन पैनल से एक परीक्षण पुश है।'],
            ['type' => 'test_push', 'actor_id' => (int) $actor['id']]
        );

        (new \App\Services\AuditService())->log([
            'user_id' => (int) $actor['id'],
            'action' => 'TEST_PUSH',
            'module' => 'NOTIFICATIONS',
            'entity_type' => 'notification',
            'new_value' => [
                'target' => $targetAll ? 'all' : $this->maskPlayerId($playerId),
                'sent' => $result['sent'],
                'ok' => $result['ok'],
            ],
        ]);

        if ($result['ok']) {
            Response::success(['message' => 'Test push sent', 'details' => $result]);
        } else {
            Response::error('PUSH_FAILED', 'Push send failed: ' . ($result['error'] ?? 'unknown'), 502, $result);
        }
    }

    private function requireActor(Request $request): array
    {
        $user = $request->getUser();
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }
        return $user;
    }

    private function requirePermission(array $actor, string $permission): void
    {
        $rbac = new RbacService();
        $rbac->assertCan($actor, $permission, 'You do not have permission');
    }

    private function maskPlayerId(string $playerId): string
    {
        $len = strlen($playerId);
        if ($len <= 8) {
            return '****';
        }
        return substr($playerId, 0, 4) . '****' . substr($playerId, -4);
    }
}
