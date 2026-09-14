<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\NotFoundException;
use App\Models\LoginHistory;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\RememberTokenService;
use App\Services\SessionService;
use App\Validators\AuthValidator;

class SessionController
{
    public function list(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $userId = (int) $user['id'];
        $currentSessionId = (int) ($user['session_id'] ?? 0);

        $sessionService = new SessionService();
        $sessions = $sessionService->activeSessions($userId);

        $data = array_map(function ($s) use ($currentSessionId) {
            return [
                'id' => (int) $s['id'],
                'device_name' => $s['device_name'],
                'platform' => $s['platform'],
                'ip' => $s['ip_address'],
                'user_agent' => $s['user_agent'],
                'created_at' => $s['created_at'],
                'last_activity_at' => $s['last_activity_at'],
                'expires_at' => $s['expires_at'],
                'status' => $s['status'],
                'is_current' => (int) $s['id'] === $currentSessionId,
            ];
        }, $sessions);

        Response::success(['sessions' => $data]);
    }

    public function revoke(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $sessionId = (int) $request->getParam('id');
        $userId = (int) $user['id'];

        $session = Database::selectOne(
            "SELECT * FROM user_sessions WHERE id = ? AND user_id = ?",
            [$sessionId, $userId]
        );

        if ($session === null) {
            throw new NotFoundException('SESSION_NOT_FOUND', 'Session not found');
        }

        if ((int) $session['id'] === (int) ($user['session_id'] ?? 0)) {
            throw new AuthenticationException('FORBIDDEN', 'Use logout to revoke the current session', 403);
        }

        $sessionService = new SessionService();
        $sessionService->revoke($sessionId, $userId, 'user_revoked');

        $remember = $session['remember'] ?? null;
        if ($remember !== null) {
            (new RememberTokenService())->revoke((int) $remember, $userId);
        }

        (new AuditService())->log([
            'user_id' => $userId,
            'action' => 'SESSION_REVOKED',
            'module' => 'SESSIONS',
            'entity_type' => 'user_session',
            'entity_id' => $sessionId,
            'reason' => 'revoked_by_user',
        ]);

        Response::success(['message' => 'Session revoked']);
    }

    public function revokeAll(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $userId = (int) $user['id'];
        $currentSessionId = (int) ($user['session_id'] ?? 0);

        $sessionService = new SessionService();
        $count = $sessionService->revokeAllOthers($userId, $currentSessionId, $userId);

        (new RememberTokenService())->revokeAllForUser($userId, $userId);

        (new AuditService())->log([
            'user_id' => $userId,
            'action' => 'SESSIONS_REVOKED_ALL',
            'module' => 'SESSIONS',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'new_value' => ['revoked' => $count],
            'reason' => 'revoke_all',
        ]);

        Response::success(['message' => "Revoked {$count} other session(s)", 'revoked' => $count]);
    }

    public function refreshCurrent(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $userId = (int) $user['id'];
        $currentSessionId = (int) ($user['session_id'] ?? 0);

        $session = Database::selectOne(
            "SELECT * FROM user_sessions WHERE id = ? AND user_id = ?",
            [$currentSessionId, $userId]
        );

        if ($session === null) {
            throw new AuthenticationException('SESSION_EXPIRED', 'Session has expired. Please login again.', 401);
        }

        $sessionTimeout = (int) get_setting('session_timeout_minutes', 30) * 60;
        Database::update(
            'user_sessions',
            [
                'last_activity_at' => date('Y-m-d H:i:s'),
                'expires_at' => date('Y-m-d H:i:s', time() + $sessionTimeout),
            ],
            'id = ? AND user_id = ?',
            [$currentSessionId, $userId]
        );

        Response::success([
            'message' => 'Session extended',
            'expires_at' => date('Y-m-d H:i:s', time() + $sessionTimeout),
            'expires_in' => $sessionTimeout,
        ]);
    }

    public function registerDevice(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $data = (new AuthValidator())->registerDevice($request->all());

        (new AuthService())->registerDeviceForUser((int) $user['id'], $data, $request->ip());

        Response::success(['message' => 'Device registered for push']);
    }

    public function loginHistory(Request $request): void
    {
        $user = $request->getUser();
        $this->requireUser($user);

        $userId = (int) $user['id'];
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));
        $status = $request->query('status');
        $from = $request->query('from');
        $to = $request->query('to');

        $where = ['lh.user_id = ?'];
        $params = [$userId];

        if ($status !== null && in_array(strtoupper((string) $status), ['SUCCESS', 'FAILURE'], true)) {
            $where[] = 'lh.status = ?';
            $params[] = strtoupper((string) $status);
        }

        if ($from !== null && $from !== '') {
            $where[] = 'lh.login_at >= ?';
            $params[] = $from . ' 00:00:00';
        }

        if ($to !== null && $to !== '') {
            $where[] = 'lh.login_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total FROM login_history lh WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT u.name AS user_name, r.name AS role_name, lh.*
             FROM login_history lh
             LEFT JOIN users u ON u.id = lh.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE {$whereSql}
             ORDER BY lh.login_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'user' => $row['user_id'] !== null ? [
                    'id' => (int) $row['user_id'],
                    'name' => $row['user_name'],
                    'role' => $row['role_name'],
                ] : null,
                'login_at' => $row['login_at'],
                'logout_at' => $row['logout_at'],
                'ip' => $row['ip_address'],
                'user_agent' => $row['user_agent'],
                'platform' => $row['platform'],
                'device_name' => $row['device_name'],
                'status' => $row['status'],
                'failure_reason' => $row['failure_reason'],
            ];
        }, $rows);

        Response::success($data, [
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ]);
    }

    private function requireUser(?array $user): void
    {
        if ($user === null) {
            throw new AuthenticationException('UNAUTHENTICATED', 'Authentication required', 401);
        }
    }
}