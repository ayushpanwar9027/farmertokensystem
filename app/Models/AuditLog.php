<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class AuditLog extends BaseModel
{
    protected string $table = 'audit_logs';
    protected bool $softDeletes = false;

    public function adminList(array $filters, ?array $userIdFilter = null, int $page = 1, int $perPage = 20): array
    {
        $where = '1=1';
        $params = [];

        if (!empty($filters['action'])) {
            $where .= ' AND al.action = ?';
            $params[] = (string) $filters['action'];
        }
        if (!empty($filters['module'])) {
            $where .= ' AND al.module = ?';
            $params[] = (string) $filters['module'];
        }
        if (!empty($filters['entity_type'])) {
            $where .= ' AND al.entity_type = ?';
            $params[] = (string) $filters['entity_type'];
        }
        if (!empty($filters['date_from'])) {
            $where .= ' AND al.created_at >= ?';
            $params[] = (string) $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where .= ' AND al.created_at <= ?';
            $params[] = (string) $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['user_id'])) {
            $where .= ' AND al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['q'])) {
            $where .= ' AND (al.user_name LIKE ? OR al.action LIKE ? OR al.module LIKE ? OR al.reason LIKE ?)';
            $q = '%' . (string) $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        if ($userIdFilter !== null) {
            if (empty($userIdFilter)) {
                $userIdFilter = [0];
            }
            $placeholders = rtrim(str_repeat('?,', count($userIdFilter)), ',');
            $where .= " AND al.user_id IN ({$placeholders})";
            foreach ($userIdFilter as $uid) {
                $params[] = (int) $uid;
            }
        }

        $countParams = $params;
        $countRow = Database::selectOne(
            $this->buildCountSql($where),
            $countParams
        );
        $total = (int) ($countRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT al.*
                FROM audit_logs al
                WHERE {$where}
                ORDER BY al.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = Database::select($sql, $params);

        return [
            'data' => $rows,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function userIdsForRoleLevel(?array $user, array $centreIds, array $scopedDistrictIds): array
    {
        if ($user === null) {
            return [];
        }

        if (($user['is_super_admin'] ?? 0) === 1 && ($user['role'] ?? '') === 'SUPER_ADMIN') {
            return [];
        }

        $where = [];
        $params = [];
        if (!empty($centreIds)) {
            $placeholders = rtrim(str_repeat('?,', count($centreIds)), ',');
            $where[] = "cs.centre_id IN ({$placeholders})";
            foreach ($centreIds as $cid) {
                $params[] = (int) $cid;
            }
        }
        if (!empty($scopedDistrictIds)) {
            $placeholders = rtrim(str_repeat('?,', count($scopedDistrictIds)), ',');
            $where[] = "f.district_id IN ({$placeholders})";
            foreach ($scopedDistrictIds as $did) {
                $params[] = (int) $did;
            }
        }
        if (empty($where)) {
            return [];
        }

        $whereSql = implode(' OR ', $where);
        $rows = Database::select(
            "SELECT DISTINCT u.id AS user_id
             FROM users u
             LEFT JOIN centre_staff cs ON cs.user_id = u.id
             LEFT JOIN farmers f ON f.user_id = u.id
             WHERE {$whereSql}",
            $params
        );

        return array_map('intval', array_column($rows, 'user_id'));
    }

    private function buildCountSql(string $where): string
    {
        return "SELECT COUNT(*) AS total FROM audit_logs al WHERE {$where}";
    }
}