# Roles & Permissions (RBAC)

## Overview

Role-Based Access Control with granular permissions and resource scoping. Backend enforces all access decisions — never trust frontend only.

## Access Decision Flow

```
Authenticated?
      │
      ▼
Role?  (what is your role hierarchy level)
      │
      ▼
Permission?  (do you have the required permission?)
      │
      ▼
Resource Scope?  (is the target within your scope?)
      │
      ▼
Allow / Deny
```

## Roles

| Role | Level | Scope | Purpose |
|------|-------|-------|---------|
| **SUPER_ADMIN** | 100 | All districts & centres | Full system control |
| **DISTRICT_ADMIN** | 70 | Assigned district only | District-level management |
| **CENTRE_MANAGER** | 50 | Assigned centre only | Centre management |
| **CENTRE_OPERATOR** | 30 | Assigned centre + operational | Day-to-day operations |
| **FARMER** | 10 | Own data only | Self-service via Flutter app |

### Role Constraints
- Super Admin cannot be created via UI (initial setup only)
- District Admin can only manage within assigned district
- Centre Manager manages assigned centre
- Centre Operator has operational permissions within centre
- **Lower-level users cannot grant higher-level permissions**
- A user can hold one primary role (role_id) + centre assignments via centre_staff

## Permissions (Granular)

Permissions are stored per-module. Super Admin assigns via checkboxes.

### Full Permission List

**FARMERS**
- `view_farmers`
- `manage_farmers`

**CENTRES**
- `view_centres`
- `manage_centres`

**SLOTS**
- `view_slots`
- `manage_slots`

**QUEUE**
- `view_queue`
- `manage_queue`

**PROCUREMENT**
- `view_procurement`
- `manage_procurement`

**PAYMENTS**
- `view_payments`
- `manage_payments`

**REPORTS**
- `view_reports`

**STAFF**
- `manage_staff`

**CORRECTIONS**
- `approve_corrections`

**AUDIT**
- `view_audit_logs`

**LANGUAGES**
- `manage_languages`

**FILES**
- `manage_files`

**SYSTEM**
- `manage_system_settings`

**SESSIONS**
- `manage_sessions`

**SECRETS**
- `manage_secrets`

**MAINTENANCE**
- `manage_maintenance`

**FARMER (self-service)**
- `manage_own_profile`
- `book_slot`
- `manage_own_bookings`
- `view_own_queue`
- `view_own_procurement`
- `view_own_payments`

## Role ↔ Permission Defaults

| Permission | SA | DA | CM | CO | Farmer |
|------------|----|----|----|----|--------|
| view_farmers | ✓ | ✓ | ✓ | - | - |
| manage_farmers | ✓ | ✓ | ✓ | - | - |
| view_centres | ✓ | ✓ | ✓ | ✓ | ✓ (public list) |
| manage_centres | ✓ | ✓ | ✓ | - | - |
| view_slots | ✓ | ✓ | ✓ | ✓ | ✓ |
| manage_slots | ✓ | ✓ | ✓ | - | - |
| view_queue | ✓ | ✓ | ✓ | ✓ | ✓ (own) |
| manage_queue | ✓ | ✓ | ✓ | ✓ | - |
| view_procurement | ✓ | ✓ | ✓ | ✓ | ✓ (own) |
| manage_procurement | ✓ | ✓ | ✓ | ✓ | - |
| view_payments | ✓ | ✓ | ✓ | ✓ | ✓ (own) |
| manage_payments | ✓ | ✓ | ✓ | - | - |
| view_reports | ✓ | ✓ | ✓ | ✓ | - |
| manage_staff | ✓ | ✓ | ✓ | - | - |
| approve_corrections | ✓ | ✓ | ✓ | - | - |
| view_audit_logs | ✓ | ✓ | ✓ | - | - |
| manage_languages | ✓ | - | - | - | - |
| manage_files | ✓ | - | - | - | - |
| manage_system_settings | ✓ | - | - | - | - |
| manage_sessions | ✓ | ✓ | - | - | - |
| manage_secrets | ✓ | - | - | - | - |
| manage_maintenance | ✓ | - | - | - | - |
| manage_own_profile | - | - | - | - | ✓ |
| book_slot | - | - | - | - | ✓ |
| manage_own_bookings | - | - | - | - | ✓ |
| view_own_queue | - | - | - | - | ✓ |
| view_own_procurement | - | - | - | - | ✓ |
| view_own_payments | - | - | - | - | ✓ |

## Resource Scoping

Beyond permissions, access is limited by resource scope.

### Scope Resolution

```php
function getScope(?User $user): Scope {
    return match($user->role) {
        'SUPER_ADMIN'      => Scope::all(),
        'DISTRICT_ADMIN'   => Scope::district($user->district_id),
        'CENTRE_MANAGER'   => Scope::centre($user->centre_id),
        'CENTRE_OPERATOR'  => Scope::centre($user->centre_id),
        'FARMER'           => Scope::self($user->id),
        default            => Scope::none(),
    };
}
```

### Scope Check Examples

| Request | SUPER_ADMIN | DISTRICT_ADMIN | CENTRE_MANAGER | CENTRE_OPERATOR | FARMER |
|---------|-------------|----------------|----------------|-----------------|--------|
| GET /centres | all | district only | own centre | own centre | all active |
| GET /bookings | all | district | centre | centre | own only |
| POST /queue/call-next | any centre | district centre | own centre | own centre | denied |
| GET /reports | all | district | centre | centre | denied |
| GET /staff | all | district | centre | denied | denied |

## Database Schema

```sql
-- roles
CREATE TABLE roles (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    level INT UNSIGNED NOT NULL DEFAULT 0,
    is_system TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
);

-- permissions
CREATE TABLE permissions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description VARCHAR(500) NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);

-- role_permissions (defaults)
CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- user_permissions (individual overrides)
CREATE TABLE user_permissions (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted TINYINT(1) DEFAULT 1,     -- 1 allow, 0 deny
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

## Permission Resolution Algorithm

```php
function userHasPermission(int $userId, string $permissionName): bool {
    // 1. Get user role
    $user = user::find($userId);
    
    // 2. Check individual override first (highest priority)
    $override = user_permissions::where('user_id', $userId)
                ->join('permissions', ...)
                ->where('permissions.name', $permissionName)->first();
    if ($override) {
        return (bool)$override->granted;   // explicit allow/deny wins
    }
    
    // 3. Check role defaults
    return role_permissions::where('role_id', $user->role_id)
           ->join('permissions', ...)
           ->where('permissions.name', $permissionName)->exists();
}
```

### Priority
1. User override (if present) — allows BOTH granting AND denying
2. Role defaults
3. Deny by default

## Staff Creation Flow

```
Admin selects "Create Staff"
      │
      ▼
Enter details (name, mobile, username)
      │
      ▼
Select Role
   ├─ DISTRICT_ADMIN   (district assignment)
   ├─ CENTRE_MANAGER   (centre + district)
   └─ CENTRE_OPERATOR  (centre)
      │
      ▼
Load Default Permissions for role
      │
      ▼
Customize: Add/Remove permission checkboxes
      │
      ▼
Save
      │
      ▼
- Create user with role
- Save user_permissions overrides (if customized)
- Assign centre via centre_staff
- Generate temporary password
- Record audit
```

## Permission Guarding Rules

- **Super Admin** can grant any permission up to their own level
- **District Admin** cannot grant permissions above its own, nor manage users outside its district
- **Centre Manager** cannot create/modify users above own level
- No user can grant a permission they don't themselves have
- No user can assign a role higher than their own level

## Backend Enforcement

Every protected endpoint:
1. AuthMiddleware → identity
2. RoleMiddleware → role satisfies minimum required
3. PermissionMiddleware → userHasPermission
4. ScopeMiddleware → resource within scope (SQL WHERE clause + object check)

Example route guard:
```php
'POST /api/v1/centres' => [
    'CentreController@store',
    ['auth', 'staff', 'permission:manage_centres', 'scope:all|district']
],
```

## Audit

- Permission changes: audited (old/new)
- Role changes: audited
- Staff creation/deactivation: audited
- Scope changes: audited
- Failed permission attempts: security log

---

**Next**: [14-permission-matrix.md](14-permission-matrix.md) for detailed matrix.