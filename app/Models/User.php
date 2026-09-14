<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User extends BaseModel
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    public function byId(int $id): ?array
    {
        return $this->find($id);
    }

    public function byMobile(string $mobile): ?array
    {
        return $this->findBy('mobile', $mobile);
    }

    public function byEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function byUsername(string $username): ?array
    {
        return $this->findBy('username', $username);
    }

    public function byMobileOrUsername(string $identifier): ?array
    {
        $user = $this->byUsername($identifier);
        if ($user !== null) {
            return $user;
        }
        return $this->byMobile($identifier);
    }

    public function roleName(int $roleId): string
    {
        $row = Database::selectOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
        return $row['name'] ?? '';
    }

    public function withRole(array $user): array
    {
        $user['role'] = $this->roleName((int) ($user['role_id'] ?? 0));
        return $user;
    }

    public function permissionNames(int $userId): array
    {
        return (new \App\Services\RbacService())->effectivePermissions($userId);
    }

    public function isActive(int $userId): bool
    {
        $user = $this->find($userId);
        return $user !== null && $user['status'] === 'ACTIVE';
    }

    public function isSuperAdmin(int $userId): bool
    {
        $user = $this->find($userId);
        return $user !== null && !empty($user['is_super_admin']);
    }

    public function updateLastLogin(int $userId): void
    {
        $this->update($userId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public function changeStatus(int $userId, string $status): bool
    {
        $row = $this->find($userId);
        if ($row === null) {
            return false;
        }
        $this->update($userId, ['status' => $status]);
        return true;
    }

    public function roleNameFor(int $userId): string
    {
        $row = $this->find($userId);
        return $row !== null ? $this->roleName((int) $row['role_id']) : '';
    }

    public function centreFor(int $userId): ?array
    {
        return (new CentreStaff())->assignmentForUser($userId);
    }
}
