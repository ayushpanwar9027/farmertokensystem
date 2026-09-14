# Permission Matrix

Complete matrix of role ↔ permission assignments and resource scoping.

## Legend

- **✓** = Granted (default)
- **✗** = Not granted (denied)
- **(S)** = Scope-limited
- **✦** = Individually configurable (Super Admin override)

## Role Permission Matrix

| # | Permission | Module | Super Admin | District Admin | Centre Manager | Centre Operator | Farmer |
|---|------------|--------|:-----------:|:--------------:|:--------------:|:---------------:|:------:|
| 1 | view_farmers | FARMERS | ✓ | ✓ | ✓ | ✗ | ✗ |
| 2 | manage_farmers | FARMERS | ✓ | ✓ | ✓ | ✗ | ✗ |
| 3 | view_centres | CENTRES | ✓ | ✓ | ✓ | ✓ | ✓ |
| 4 | manage_centres | CENTRES | ✓ | ✓ | ✓ | ✗ | ✗ |
| 5 | view_slots | SLOTS | ✓ | ✓ | ✓ | ✓ | ✓ |
| 6 | manage_slots | SLOTS | ✓ | ✓ | ✓ | ✗ | ✗ |
| 7 | view_queue | QUEUE | ✓ | ✓ | ✓ | ✓ | ✓ |
| 8 | manage_queue | QUEUE | ✓ | ✓ | ✓ | ✓ | ✗ |
| 9 | view_procurement | PROCUREMENT | ✓ | ✓ | ✓ | ✓ | ✓ |
| 10 | manage_procurement | PROCUREMENT | ✓ | ✓ | ✓ | ✓ | ✗ |
| 11 | view_payments | PAYMENTS | ✓ | ✓ | ✓ | ✓ | ✓ |
| 12 | manage_payments | PAYMENTS | ✓ | ✓ | ✓ | ✗ | ✗ |
| 13 | view_reports | REPORTS | ✓ | ✓ | ✓ | ✓ | ✗ |
| 14 | manage_staff | STAFF | ✓ | ✓ | ✓ | ✗ | ✗ |
| 15 | approve_corrections | CORRECTIONS | ✓ | ✓ | ✓ | ✗ | ✗ |
| 16 | view_audit_logs | AUDIT | ✓ | ✓ | ✓ | ✗ | ✗ |
| 17 | manage_languages | LANGUAGES | ✓ | ✗ | ✗ | ✗ | ✗ |
| 18 | manage_files | FILES | ✓ | ✗ | ✗ | ✗ | ✗ |
| 19 | manage_system_settings | SETTINGS | ✓ | ✗ | ✗ | ✗ | ✗ |
| 20 | manage_sessions | SESSIONS | ✓ | ✓ | ✗ | ✗ | ✗ |
| 21 | manage_secrets | SECRETS | ✓ | ✗ | ✗ | ✗ | ✗ |
| 22 | manage_maintenance | MAINTENANCE | ✓ | ✗ | ✗ | ✗ | ✗ |
| 23 | manage_own_profile | PROFILE | ✗ | ✗ | ✗ | ✗ | ✓ |
| 24 | book_slot | BOOKINGS | ✗ | ✗ | ✗ | ✗ | ✓ |
| 25 | manage_own_bookings | BOOKINGS | ✗ | ✗ | ✗ | ✗ | ✓ |
| 26 | view_own_queue | QUEUE | ✗ | ✗ | ✗ | ✗ | ✓ |
| 27 | view_own_procurement | PROCUREMENT | ✗ | ✗ | ✗ | ✗ | ✓ |
| 28 | view_own_payments | PAYMENTS | ✗ | ✗ | ✗ | ✗ | ✓ |

## Permission → Endpoint Mapping

| Permission | Allowed Endpoints (examples) |
|-----------|------------------------------|
| **view_farmers** | `GET /staff/farmers`, `GET /reports/farmers` |
| **manage_farmers** | `POST /staff/farmers`, `PUT /staff/farmers/{id}`, `PATCH /staff/farmers/{id}/verify` |
| **view_centres** | `GET /centres`, `GET /centres/{id}` |
| **manage_centres** | `POST /centres`, `PUT /centres/{id}`, `PATCH /centres/{id}/status` |
| **view_slots** | `GET /slots`, `GET /slots/{id}` |
| **manage_slots** | `POST /slots`, `PUT /slots/{id}`, `PATCH /slots/{id}/status`, `DELETE /slots/{id}` |
| **view_queue** | `GET /queue/live`, `GET /queue/stats` |
| **manage_queue** | `POST /queue/call-next`, `POST /queue/{id}/arrived`, `POST /queue/{id}/start`, `POST /queue/{id}/complete`, `POST /queue/{id}/skip` |
| **view_procurement** | `GET /procurements`, `GET /procurements/{id}` |
| **manage_procurement** | `POST /bookings/{id}/procurements`, `PATCH /procurements/{id}/status`, `PATCH /procurements/{id}/reject` |
| **view_payments** | `GET /payments`, `GET /payments/{id}` |
| **manage_payments** | `PATCH /payments/{id}/status` (Super/District/Manager only) |
| **view_reports** | `GET /reports/*` |
| **manage_staff** | `POST /staff`, `PUT /staff/{id}`, `PATCH /staff/{id}/permissions` |
| **approve_corrections** | `POST /corrections/{id}/approve`, `POST /corrections/{id}/reject` |
| **view_audit_logs** | `GET /audit-logs` |
| **manage_languages** | `POST /admin/languages`, `PUT /admin/languages/{id}` |
| **manage_files** | All `/files/*` endpoints |
| **manage_system_settings** | `GET /settings/admin`, `PUT /settings` |
| **manage_sessions** | `GET /sessions`, `POST /sessions/{id}/revoke` |
| **manage_secrets** | `GET /secrets`, `PUT /secrets/{key}` |
| **manage_maintenance** | `PATCH /maintenance` |
| **manage_own_profile** | `GET /profile`, `PUT /profile` |
| **book_slot** | `POST /bookings` |
| **manage_own_bookings** | `GET /bookings`, `GET /bookings/{id}`, `PATCH /bookings/{id}/cancel` |
| **view_own_queue** | `GET /queue/live` (with booking filter) |
| **view_own_procurement** | `GET /procurements` (own only) |
| **view_own_payments** | `GET /payments` (own only) |

## Resource Scope Matrix

| Endpoint | SUPER_ADMIN | DISTRICT_ADMIN | CENTRE_MANAGER | CENTRE_OPERATOR | FARMER |
|----------|-------------|----------------|----------------|-----------------|--------|
| **Farmers** | All | District | Centre | ✗ | ✗ |
| **Centres** | All | District | Own centre | Own centre (view) | All active |
| **Slots** | All | District centres | Own centre | Own centre (view) | All active |
| **Bookings** | All | District | Own centre | Own centre | Own only |
| **Queue** | All | District | Own centre | Own centre | Own entry |
| **Procurement** | All | District | Own centre | Own centre | Own only |
| **Payments** | All | District | Own centre | Own centre | Own only |
| **Reports** | All | District | Own centre | Own centre | ✗ |
| **Staff** | All | District | Own centre | ✗ | ✗ |
| **Audit** | All | District | Own centre | ✗ | ✗ |
| **Notifications** | All | District | Own centre | Own centre | Own only |
| **Files** | All | ✗ | ✗ | ✗ | ✗ |
| **Settings** | All | ✗ | ✗ | ✗ | ✗ |
| **Secrets** | All | ✗ | ✗ | ✗ | ✗ |
| **Languages** | All | ✗ | ✗ | ✗ | ✗ |
| **Maintenance** | All | ✗ | ✗ | ✗ | ✗ |

## Financial / Sensitive Action Approval Matrix

Actions requiring higher authority:

| Action | Who can perform | Approval required |
|--------|----------------|-------------------|
| Mark payment PAID | Manager / District / Super | Manager+ (audit) |
| Reverse a payment | District / Super | District+ (audit) |
| Accept procurement correction | Manager+ | Manager+ (audit) |
| Override queue order | Manager / District / Super | Manager+ (audit + reason) |
| Reject farmer verification | Manager / District / Super | Manager+ (audit) |
| Delete/modify completed procurement | Super only | Super (audit) |

## Permission Overrides (User-specific)

Super Admin can customize permissions per staff user via `user_permissions`:

```
Staff: Operator One (role: CENTRE_OPERATOR)
Default: view_queue ✓, manage_queue ✓, view_procurement ✓, ...
Override: + manage_procurement ✓ (grant extra)
Override: - manage_queue ✗ (deny specific)
```

### Rules for Overrides
- Cannot grant permissions above the target's role level (highest they could ever have)
- Cannot grant permissions the assigning admin does not hold
- Deny (granted=0) always overrides role default
- Overrides recorded & audited

## Role Hierarchy & Assignment Rules

```
SUPER_ADMIN (level 100)  ── can assign: DA, CM, CO
DISTRICT_ADMIN (level 70) ── can assign: CM, CO (within district)
CENTRE_MANAGER (level 50) ── can assign: CO (within own centre)  [optional]
CENTRE_OPERATOR (level 30) ── cannot assign staff
FARMER (level 10)          ── cannot assign
```

- A user cannot assign a role with level >= their own
- A user cannot manage a user with a higher level
- System roles (SUPER_ADMIN, FARMER) cannot be deleted

## CRUD Action Permission by Entity

Each "manage_" permission typically implies View + Create + Update + Delete(soft)/deactivate on scoped resources. The "view_" permission controls read-only access.

| Entity | View | Create | Update | Delete/Deactivate |
|--------|------|--------|--------|-------------------|
| Centre | view_centres | manage_centres | manage_centres | manage_centres |
| Slot | view_slots | manage_slots | manage_slots | manage_slots |
| Booking | view_bookings (or own) | book_slot (farmer) | manage_own_bookings | manage (cancel) |
| Queue | view_queue | manage_queue (call) | manage_queue | manage_queue |
| Procurement | view_procurement | manage_procurement | manage_procurement | Super only (soft) |
| Payment | view_payments | n/a | manage_payments | District+ (reverse) |
| Staff | view_staff | manage_staff | manage_staff | manage_staff |
| Audit | view_audit_logs | n/a | n/a | n/a |
| File | manage_files | manage_files | manage_files | manage_files |
| Language | (view public) | manage_languages | manage_languages | manage_languages |
| Setting | (view public) | manage_system_settings | manage_system_settings | n/a |
| Secret | manage_secrets | manage_secrets | manage_secrets | manage_secrets |

---

**Next**: [15-business-rules.md](15-business-rules.md) for all business rules.