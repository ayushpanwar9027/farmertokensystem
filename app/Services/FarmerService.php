<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Models\Farmer;
use App\Models\Role;
use App\Models\User;
use App\Validators\FarmerValidator;

class FarmerService
{
    private FarmerValidator $validator;
    private RbacService $rbac;
    private ScopeService $scope;
    private AuditService $audit;
    private SessionService $sessions;
    private User $users;
    private Farmer $farmers;

    public function __construct()
    {
        $this->validator = new FarmerValidator();
        $this->rbac = new RbacService();
        $this->scope = new ScopeService();
        $this->audit = new AuditService();
        $this->sessions = new SessionService();
        $this->users = new User();
        $this->farmers = new Farmer();
    }

    public function create(array $actor, array $data): array
    {
        $data = $this->validator->create($data);

        $name = trim((string) $data['name']);
        $mobile = $this->normalizeMobile((string) $data['mobile']);

        if ($this->users->byMobile($mobile) !== null) {
            throw new ConflictException('DUPLICATE_FARMER', 'A user with this mobile already exists');
        }

        $district = $this->resolveDistrict((int) $data['district_id']);
        $state = trim((string) ($data['state'] ?? '')) !== '' ? trim((string) $data['state']) : (string) $district['state'];

        $password = isset($data['password']) && trim((string) $data['password']) !== ''
            ? (string) $data['password']
            : $this->generatePassword();

        $roleId = $this->farmerRoleId();
        $now = date('Y-m-d H:i:s');

        Database::beginTransaction();
        try {
            $userId = $this->users->insert([
                'name' => $name,
                'mobile' => $mobile,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role_id' => $roleId,
                'status' => 'ACTIVE',
                'verification_status' => 'APPROVED',
                'mobile_verified_at' => $now,
                'password_set_at' => $now,
                'created_by' => (int) $actor['id'],
            ]);

            $farmerId = $this->farmers->insert([
                'user_id' => $userId,
                'village' => trim((string) $data['village']),
                'district_id' => (int) $district['id'],
                'state' => $state,
                'alternative_mobile' => isset($data['alternative_mobile']) && trim((string) $data['alternative_mobile']) !== ''
                    ? $this->normalizeMobile((string) $data['alternative_mobile'])
                    : null,
                'pincode' => isset($data['pincode']) && trim((string) $data['pincode']) !== '' ? trim((string) $data['pincode']) : null,
                'land_area_acres' => isset($data['land_area_acres']) && $data['land_area_acres'] !== '' ? (float) $data['land_area_acres'] : null,
                'primary_crops' => isset($data['primary_crops']) && is_array($data['primary_crops'])
                    ? json_encode($data['primary_crops'], JSON_UNESCAPED_UNICODE)
                    : null,
                'aadhaar_last4' => isset($data['aadhaar_last4']) && trim((string) $data['aadhaar_last4']) !== '' ? trim((string) $data['aadhaar_last4']) : null,
                'verification_status' => 'APPROVED',
                'verified_at' => $now,
                'verified_by' => (int) $actor['id'],
            ]);

            $this->audit->log([
                'user_id' => (int) $actor['id'],
                'user_name' => $actor['name'] ?? '',
                'user_role' => $actor['role'] ?? '',
                'action' => 'FARMER_CREATED',
                'module' => 'FARMERS',
                'entity_type' => 'user',
                'entity_id' => $userId,
                'new_value' => [
                    'name' => $name,
                    'mobile' => $mobile,
                    'village' => trim((string) $data['village']),
                    'district_id' => (int) $district['id'],
                    'state' => $state,
                    'password_set' => true,
                ],
                'reason' => 'admin_create_farmer',
            ]);

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }

        $this->rbac->clearCache($userId);

        $farmer = $this->fullFarmer($userId);

        return [
            'farmer' => $farmer,
            'farmer_id' => (int) $farmerId,
            'mobile' => $mobile,
            'credentials' => [
                'mobile' => $mobile,
                'password' => $password,
            ],
            'message' => 'Farmer account created. The farmer can login with the mobile number and password in the app.',
        ];
    }

    public function find(int $userId): array
    {
        $row = $this->fullFarmer($userId);
        if ($row === null) {
            throw new NotFoundException('FARMER_NOT_FOUND', 'Farmer not found');
        }
        return $row;
    }

    public function resetPassword(array $actor, int $userId, array $data): array
    {
        $data = $this->validator->password($data);

        $password = (string) $data['password'];

        $this->assertWithinScope($actor, $userId);

        $farmer = $this->find($userId);

        $now = date('Y-m-d H:i:s');
        Database::update(
            'users',
            ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'password_set_at' => $now, 'updated_at' => $now],
            'id = ?',
            [$userId]
        );

        $this->sessions->revokeAllForUser($userId, (int) $actor['id'], 'farmer_password_reset');

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FARMER_PASSWORD_RESET',
            'module' => 'FARMERS',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'reason' => 'admin_reset_farmer_password',
        ]);

        return [
            'farmer' => $this->fullFarmer($userId),
            'credentials' => [
                'mobile' => $farmer['mobile'],
                'password' => $password,
            ],
            'message' => 'Farmer password updated. Existing sessions were revoked.',
        ];
    }

    public function changeStatus(array $actor, int $userId, string $status): array
    {
        $this->assertWithinScope($actor, $userId);

        $farmer = $this->find($userId);

        $now = date('Y-m-d H:i:s');
        Database::update(
            'users',
            ['status' => $status, 'updated_at' => $now],
            'id = ?',
            [$userId]
        );

        if ($status === 'INACTIVE') {
            $this->sessions->revokeAllForUser($userId, (int) $actor['id'], 'farmer_deactivated');
        }

        $this->audit->log([
            'user_id' => (int) $actor['id'],
            'user_name' => $actor['name'] ?? '',
            'user_role' => $actor['role'] ?? '',
            'action' => 'FARMER_STATUS_CHANGED',
            'module' => 'FARMERS',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'old_value' => ['status' => $farmer['status']],
            'new_value' => ['status' => $status],
            'reason' => 'admin_change_farmer_status',
        ]);

        return [
            'farmer' => $this->fullFarmer($userId),
            'message' => 'Farmer status updated',
        ];
    }

    public function list(array $actor, array $filters, int $page, int $perPage): array
    {
        $filters = $this->validator->listFilters($filters);

        $where = ['u.deleted_at IS NULL'];
        $params = [];

        $scope = $this->scope->scopeFor($actor);
        if ($scope['type'] === 'district') {
            $districtIds = array_filter($scope['district_ids']);
            if (empty($districtIds)) {
                $where[] = '1 = 0';
            } else {
                $placeholders = implode(',', array_fill(0, count($districtIds), '?'));
                $where[] = 'f.district_id IN (' . $placeholders . ')';
                $params = array_merge($params, array_values($districtIds));
            }
        } elseif ($scope['type'] === 'centre') {
            $where[] = '1 = 1';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        $status = strtoupper((string) ($filters['status'] ?? ''));
        $verificationStatus = strtoupper((string) ($filters['verification_status'] ?? ''));
        $districtId = isset($filters['district_id']) && $filters['district_id'] !== '' ? (int) $filters['district_id'] : null;

        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.mobile LIKE ? OR f.village LIKE ? OR f.aadhaar_last4 LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($verificationStatus !== '') {
            $where[] = 'f.verification_status = ?';
            $params[] = $verificationStatus;
        }
        if ($districtId !== null) {
            if ($this->rbac->isSuperAdmin($actor) || $scope['type'] === 'all') {
                $where[] = 'f.district_id = ?';
                $params[] = $districtId;
            } elseif ($scope['type'] === 'district') {
                $allowed = array_filter($scope['district_ids']);
                if (!in_array($districtId, $allowed, true)) {
                    throw new AuthorizationException('You do not have access to this district');
                }
                $where[] = 'f.district_id = ?';
                $params[] = $districtId;
            } else {
                $where[] = 'f.district_id = ?';
                $params[] = $districtId;
            }
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = Database::selectOne(
            "SELECT COUNT(*) AS total
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             INNER JOIN farmers f ON f.user_id = u.id AND f.deleted_at IS NULL
             WHERE {$whereSql}",
            $params
        );
        $total = (int) ($totalRow['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $rows = Database::select(
            "SELECT u.id, u.name, u.mobile, u.email, u.username, u.role_id, r.name AS role_name,
                    u.status, u.verification_status AS user_verification_status,
                    u.created_by, u.password_set_at, u.last_login_at, u.created_at,
                    f.id AS farmer_id, f.village, f.state, f.district_id, f.alternative_mobile,
                    f.land_area_acres, f.primary_crops, f.aadhaar_last4,
                    f.verification_status AS farmer_verification_status, f.verified_at
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             INNER JOIN farmers f ON f.user_id = u.id AND f.deleted_at IS NULL
             WHERE {$whereSql}
             ORDER BY u.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->shape($row);
        }

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }

    public function fullFarmer(int $userId): ?array
    {
        $row = Database::selectOne(
            "SELECT u.id, u.name, u.mobile, u.email, u.username, u.role_id, r.name AS role_name,
                    u.status, u.verification_status AS user_verification_status,
                    u.created_by, u.password_set_at, u.last_login_at, u.created_at,
                    f.id AS farmer_id, f.village, f.state, f.district_id, f.alternative_mobile,
                    f.land_area_acres, f.primary_crops, f.aadhaar_last4,
                    f.verification_status AS farmer_verification_status, f.verified_at,
                    d.name AS district_name, d.code AS district_code
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             INNER JOIN farmers f ON f.user_id = u.id AND f.deleted_at IS NULL
             LEFT JOIN districts d ON d.id = f.district_id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1",
            [(int) $userId]
        );

        return $row !== null ? $this->shape($row) : null;
    }

    public function assertWithinScope(array $actor, int $userId): void
    {
        if ($this->rbac->isSuperAdmin($actor)) {
            return;
        }

        $scopeInfo = $this->scope->scopeFor($actor);
        if ($scopeInfo['type'] === 'all') {
            return;
        }

        if ($scopeInfo['type'] === 'district') {
            $districtIds = array_filter($scopeInfo['district_ids']);
            $row = Database::selectOne(
                "SELECT district_id FROM farmers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1",
                [$userId]
            );
            if ($row !== null && in_array((int) $row['district_id'], $districtIds, true)) {
                return;
            }
            throw new AuthorizationException('This farmer is outside your district scope');
        }

        if ((int) ($this->users->find($userId)['created_by'] ?? 0) === (int) $actor['id']) {
            return;
        }

        throw new AuthorizationException('You cannot manage this farmer');
    }

    private function shape(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'farmer_id' => (int) $row['farmer_id'],
            'name' => $row['name'],
            'mobile' => $row['mobile'],
            'email' => $row['email'],
            'username' => $row['username'],
            'role' => $row['role_name'],
            'status' => $row['status'],
            'verification_status' => $row['farmer_verification_status'] ?: ($row['user_verification_status'] ?? 'PENDING'),
            'village' => $row['village'],
            'state' => $row['state'],
            'district' => [
                'id' => (int) $row['district_id'],
                'name' => $row['district_name'] ?? '',
                'code' => $row['district_code'] ?? '',
            ],
            'alternative_mobile' => $row['alternative_mobile'],
            'land_area_acres' => $row['land_area_acres'] !== null ? (float) $row['land_area_acres'] : null,
            'primary_crops' => $row['primary_crops'] !== null ? json_decode((string) $row['primary_crops'], true) : [],
            'aadhaar_last4' => $row['aadhaar_last4'],
            'password_set' => !empty($row['password_set_at']),
            'verified_at' => $row['verified_at'],
            'last_login_at' => $row['last_login_at'],
            'created_at' => $row['created_at'],
        ];
    }

    private function resolveDistrict(int $districtId): array
    {
        $row = Database::selectOne(
            "SELECT id, name, code, state FROM districts WHERE id = ? LIMIT 1",
            [$districtId]
        );
        if ($row === null) {
            throw new NotFoundException('DISTRICT_NOT_FOUND', 'District not found');
        }
        return $row;
    }

    private function farmerRoleId(): int
    {
        $row = (new Role())->byName('FARMER');
        if ($row === null) {
            throw new BadRequestException('ROLE_NOT_SEEDED', 'FARMER role not seeded');
        }
        return (int) $row['id'];
    }

    private function generatePassword(): string
    {
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $digits = '23456789';
        return $lower[random_int(0, strlen($lower) - 1)]
            . $upper[random_int(0, strlen($upper) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)]
            . substr(str_shuffle($lower . $upper . $digits), 0, 7);
    }

    private function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/[\s\-]/', '', $mobile);
        if (str_starts_with($mobile, '+91')) {
            $mobile = substr($mobile, 3);
        } elseif (str_starts_with($mobile, '91') && strlen($mobile) === 12) {
            $mobile = substr($mobile, 2);
        }
        return $mobile;
    }
}