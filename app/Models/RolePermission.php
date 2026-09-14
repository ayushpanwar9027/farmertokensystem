<?php

declare(strict_types=1);

namespace App\Models;

class RolePermission extends BaseModel
{
    protected string $table = 'role_permissions';
    protected bool $softDeletes = false;
}
