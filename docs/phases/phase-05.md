# Phase 05 — Roles, Permissions & Access Control (RBAC)

## 1. Objective

Implement the RBAC system: role/permission checks, permission middleware, resource scoping (district/centre), and the Super Admin permission-override system.

## 2. Prerequisites

- Phase 02 (roles/permissions seeded)
- Phase 04 (real authenticated context with role)

## 3. Features

- RBAC check engine: role → permissions, direct user permission overrides (+/-)
- PermissionMiddleware / RequiresPermissionResolver
- Scope enforcement: district-level (DISTRICT_ADMIN), centre-level (CENTRE_MANAGER/OPERATOR)
- Deny-by-default on unknown
- Super Admin full access
- Caching permission sets per request/session
- Endpoint permission map from [14-permission-matrix.md]
- Extra: district access check endpoint helper

## 4. Files to Create

```
app/Services/RbacService.php
app/Services/ScopeService.php
app/Middleware/RoleMiddleware.php
app/Middleware/PermissionMiddleware.php
app/Middleware/ScopeMiddleware.php (implemented)
app/Models/Role.php
app/Models/Permission.php
app/Models/RolePermission.php
app/Models/UserPermission.php
app/Controllers/Admin/RolePermissionController.php (manage staff roles/overrides)
```

## 5. Files to Modify

- `config/middleware.php` (attach permission/scope middleware)
- `config/routes.php` (apply per endpoint group)
- `app/Middleware/AuthMiddleware.php` (attach context user with permissions)

## 6. Database Changes

- Uses: roles, permissions, role_permissions, user_permissions (Phase 02)

## 7. API Changes

- Admin endpoints for staff role/permission management:
  - `GET /admin/staff`
  - `POST /admin/staff` (create with role)
  - `PUT /admin/staff/{id}/permissions` (override add/remove)
  - `GET /admin/roles`
  - `GET /admin/permissions` (grouped)
- Every protected endpoint now enforces its permission + scope when hit

## 8. Backend Logic

- RbacService:
  - `can(user, permission, resourceUri?)` → bool
  - super admin bypass
  - merge role_permissions + user_permissions (additive/negative)
- ScopeService:
  - `assertUserScope(user, district_id)` / `assertCentreScope(user, centre_id)`
  - SUPER_ADMIN: no scope
  - DISTRICT_ADMIN: own district
  - CENTRE_MANAGER/OPERATOR: own centre (+ district consistent)
  - FARMER: only own data
- Cache permissions for user on session load

## 9. Flutter Changes

- None (Phase 15). Farmer role mostly data-scoped.

## 10. Staff/Admin Changes

- None (Phase 16), but APIs ready for role management UI

## 11. Permissions

- This phase IS the permissions engine. Full matrix enforced:
  - All `manage:*` for super_admin/district_admin
  - Set from [14-permission-matrix.md] defaults
  - On-demand add/remove via user_permissions

## 12. Validation

- Valid permission keys
- Valid role id
- Cannot set SUPER_ADMIN role via API (exclusive/guarded)
- Cannot remove last super admin

## 13. Error Handling

- FORBIDDEN (403) + scoped message
- SINGLE_SUPER_ADMIN guard error

## 14. Security

- Deny by default
- Permission resolution deterministic (no ambiguity between additive/negative — negative wins)
- No privilege leakage across districts
- Role change invalidates user's cached permissions + token re-check

## 15. Logging/Audit

- audit per role/permission change (who, whom, what)
- security.log on denied attempts (optionally on)

## 16. Notifications

- Notify target user on role/permission change (optional Phase 14 hook)

## 17. Configuration Changes

- `permission_negative_priority=true` (rule: specific negative overrides role grants)
- `super_admin_role_id=1` guard

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] Permission checks enforced on a sample set of endpoints
- [ ] Super Admin bypass works
- [ ] District scope blocks cross-district access
- [ ] Centre scope blocks other-centre access
- [ ] Deny-by-default verified
- [ ] Negative override works
- [ ] Role/permission management API works
- [ ] Single Super Admin guard works

## 20. Testing Checklist

- [ ] FARMER calls admin endpoint → 403
- [ ] CENTRE_OPERATOR mutates payment of other centre → 403
- [ ] DISTRICT_ADMIN accesses other district centre → 403
- [ ] SUPER_ADMIN accesses everything → 200
- [ ] Remove permission via override → 403
- [ ] Grant direct permission → allowed
- [ ] Try to delete last SUPER_ADMIN → 409/400
- [ ] Role change clears cached perms → old token re-evaluated correctly

## 21. What NOT to Implement

- No booking/queue/procurement features
- No settings/secrets yet (Phase 06)
- No language/file mgmt yet (Phase 07)

---

**Depends on**: Phase 02, 04
**Feeds into**: Phase 06+