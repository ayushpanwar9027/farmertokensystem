<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;

class ScopeService
{
    private RbacService $rbac;

    public function __construct(?RbacService $rbac = null)
    {
        $this->rbac = $rbac ?? new RbacService();
    }

    public function canAccessCentre(?array $user, int $centreId): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->rbac->isSuperAdmin($user)) {
            return true;
        }

        $scope = $this->scopeFor($user);

        if ($scope['type'] === 'all') {
            return true;
        }

        if ($scope['type'] === 'self') {
            return false;
        }

        if ($scope['type'] === 'district') {
            return in_array($this->centreDistrictId($centreId), $scope['district_ids'], true);
        }

        if ($scope['type'] === 'centre') {
            return in_array($centreId, $scope['centre_ids'], true);
        }

        return false;
    }

    public function assertCentreScope(?array $user, int $centreId): void
    {
        if (!$this->canAccessCentre($user, $centreId)) {
            throw new AuthorizationException('You do not have access to this centre');
        }
    }

    public function assertUserScope(?array $user, int $districtId): void
    {
        if ($user === null) {
            throw new AuthorizationException('Authentication required');
        }

        if ($this->rbac->isSuperAdmin($user)) {
            return;
        }

        $scope = $this->scopeFor($user);

        if ($scope['type'] === 'all') {
            return;
        }

        if ($scope['type'] === 'self') {
            throw new AuthorizationException('You do not have access to this district');
        }

        if ($scope['type'] === 'district') {
            if (!in_array($districtId, $scope['district_ids'], true)) {
                throw new AuthorizationException('You do not have access to this district');
            }
            return;
        }

        if ($scope['type'] === 'centre') {
            $districtIds = $this->centreDistrictIds($scope['centre_ids']);
            if (!in_array($districtId, $districtIds, true)) {
                throw new AuthorizationException('You do not have access to this district');
            }
            return;
        }

        throw new AuthorizationException('You do not have access to this district');
    }

    public function districtIdsFor(?array $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($this->rbac->isSuperAdmin($user)) {
            return $this->allDistrictIds();
        }

        $scope = $this->scopeFor($user);

        return match ($scope['type']) {
            'all' => $this->allDistrictIds(),
            'district' => $scope['district_ids'],
            'centre' => $this->centreDistrictIds($scope['centre_ids']),
            default => [],
        };
    }

    public function centreIdsFor(?array $user): array
    {
        if ($user === null) {
            return [];
        }

        if ($this->rbac->isSuperAdmin($user)) {
            return $this->allCentreIds();
        }

        $scope = $this->scopeFor($user);

        if ($scope['type'] === 'all') {
            return $this->allCentreIds();
        }

        if ($scope['type'] === 'self') {
            return [];
        }

        return $scope['centre_ids'];
    }

    public function scopeFor(array $user): array
    {
        if ($this->rbac->isSuperAdmin($user)) {
            return ['type' => 'all', 'district_ids' => [], 'centre_ids' => []];
        }

        $role = strtoupper((string) ($user['role'] ?? ''));

        if ($role === 'DISTRICT_ADMIN') {
            return [
                'type' => 'district',
                'district_ids' => $this->assignedDistrictIds((int) $user['id']),
                'centre_ids' => [],
            ];
        }

        if ($role === 'CENTRE_MANAGER' || $role === 'CENTRE_OPERATOR') {
            return [
                'type' => 'centre',
                'district_ids' => [],
                'centre_ids' => $this->assignedCentreIds((int) $user['id'], $role),
            ];
        }

        if ($role === 'FARMER') {
            return ['type' => 'self', 'district_ids' => [], 'centre_ids' => []];
        }

        return ['type' => 'none', 'district_ids' => [], 'centre_ids' => []];
    }

    private function assignedDistrictIds(int $userId): array
    {
        $rows = Database::select(
            "SELECT DISTINCT c.district_id
             FROM procurement_centres c
             INNER JOIN centre_staff cs ON cs.centre_id = c.id
             WHERE cs.user_id = ? AND cs.deleted_at IS NULL AND c.deleted_at IS NULL",
            [$userId]
        );

        $ids = array_map(fn($r) => (int) $r['district_id'], $rows);

        if (empty($ids)) {
            $ids = $this->districtIdsViaFarmers($userId);
        }

        return array_values(array_unique($ids));
    }

    private function districtIdsViaFarmers(int $userId): array
    {
        $row = Database::selectOne(
            "SELECT district_id FROM farmers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1",
            [$userId]
        );
        return $row !== null ? [(int) $row['district_id']] : [];
    }

    private function assignedCentreIds(int $userId, string $role): array
    {
        $rows = Database::select(
            "SELECT c.id
             FROM procurement_centres c
             INNER JOIN centre_staff cs ON cs.centre_id = c.id
             WHERE cs.user_id = ? AND cs.role = ? AND cs.deleted_at IS NULL AND c.deleted_at IS NULL",
            [$userId, $role]
        );

        $ids = array_map(fn($r) => (int) $r['id'], $rows);

        if (empty($ids)) {
            $row = Database::selectOne(
                "SELECT cs.centre_id
                 FROM centre_staff cs
                 WHERE cs.user_id = ? AND cs.deleted_at IS NULL
                 LIMIT 1",
                [$userId]
            );
            if ($row !== null) {
                $ids = [(int) $row['centre_id']];
            }
        }

        return array_values(array_unique($ids));
    }

    private function centreDistrictId(int $centreId): ?int
    {
        $row = Database::selectOne(
            "SELECT district_id FROM procurement_centres WHERE id = ? AND deleted_at IS NULL LIMIT 1",
            [$centreId]
        );
        return $row !== null ? (int) $row['district_id'] : null;
    }

    private function centreDistrictIds(array $centreIds): array
    {
        if (empty($centreIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($centreIds), '?'));
        $rows = Database::select(
            "SELECT DISTINCT district_id FROM procurement_centres
             WHERE id IN ({$placeholders}) AND deleted_at IS NULL",
            $centreIds
        );
        return array_map(fn($r) => (int) $r['district_id'], $rows);
    }

    private function allDistrictIds(): array
    {
        $rows = Database::select("SELECT id FROM districts WHERE is_active = 1 ORDER BY id");
        return array_map(fn($r) => (int) $r['id'], $rows);
    }

    private function allCentreIds(): array
    {
        $rows = Database::select("SELECT id FROM procurement_centres WHERE deleted_at IS NULL ORDER BY id");
        return array_map(fn($r) => (int) $r['id'], $rows);
    }
}
