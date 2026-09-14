# Phase 08 — Procurement Centres + Staff Management

## 1. Objective

Implement procurement centre management (create/edit/view/status) and staff/centre-staff management with role assignment and centre association.

## 2. Prerequisites

- Phase 04-05 (auth + RBAC)
- Phase 06-07 (settings, files for logos/docs)

## 3. Features

- District management (list; seeded)
- Centre CRUD: name, code, district, address, contact, opening days/hours, status (ACTIVE/INACTIVE/CLOSED)
- Centre slot management (basic defaults; full slot engine Phase 09)
- Staff management: create staff user, assign role, assign centrE (for centre roles), activate/deactivate
- Centre timings affect future slot generation
- Fields for contact/harvest info

## 4. Files to Create

```
app/Controllers/Admin/CentreController.php
app/Controllers/Admin/StaffController.php
app/Controllers/DistrictController.php
app/Services/CentreService.php
app/Services/StaffService.php
app/Models/District.php
app/Models/ProcurementCentre.php
app/Models/CentreStaff.php
app/Models/User.php (add role/status helpers)
app/Validators/CentreValidator.php
app/Validators/StaffValidator.php
```

## 5. Files to Modify

- `config/routes.php` (centre/staff/district routes)
- `app/Middleware/ScopeMiddleware.php` (centre association helper)

## 6. Database Changes

- Uses districts, procurement_centres, centre_staff, users (Phase 02)

## 7. API Changes

- `GET /districts` (public)
- `GET /centres?district_id=&status=&q=&page=` (app + portal)
- `POST /admin/centres` + `PUT /admin/centres/{id}` + `GET /admin/centres/{id}`
- `POST /admin/centres/{id}/status` (ACTIVE/INACTIVE/CLOSED)
- `GET /admin/staff` + `POST /admin/staff` (create user with role) + `PUT /admin/staff/{id}` + `PUT /admin/staff/{id}/status` + `PUT /admin/staff/{id}/centre`
- `GET /admin/staff/{id}` (detail incl. permissions preview)

## 8. Backend Logic

- CentreService: create/update with district validation; generate centre code (unique, e.g., `GKP01`); status transitions guarded (INACTIVE stops new bookings later; CLOSED stops today's)
- StaffService: create base user (no password until set by user via staff login + forgot-password), assign role, assign centre only for CENTRE_MANAGER/CENTRE_OPERATOR, single-active-centre-role constraint
- Scope: DISTRICT_ADMIN manages within district; CENTRE_ roles limited to own centre; SUPER_ADMIN all
- District admin cannot create district; only super admin

## 9. Flutter Changes

- None (Phase 15): consumes `/centres` list

## 10. Staff/Admin Changes

- None (Phase 16): centre/staff management UI uses these APIs

## 11. Permissions

- `centres.view` (all roles; farmer too), `centres.manage` (admin+), `centres.status.manage`
- `staff.manage` (super_admin, district_admin scoped), `staff.view`
- `districts.manage` (super_admin only)

## 12. Validation

- Centre: name required, code unique, district exists, valid status, phone/email formats
- Staff: role valid + allowed by manager enum (cannot assign SUPER_ADMIN or higher than self in hierarchy), centre required for centre roles, valid status
- Centre code pattern `^[A-Z0-9]{2,10}$`

## 13. Error Handling

- CENTRE_NOT_FOUND, CENTRE_CODE_EXISTS, ROLE_NOT_ALLOWED, STAFF_NOT_FOUND, STAFF_STATUS_INVALID, DISTRICT_NOT_FOUND
- Deactivate centre with active future bookings → blocked (or warn; decision: block, must cancel bookings first in Phase 10)

## 14. Security

- Staff creation never sets a known default password (use invite + forgot-password)
- Email verification optional for staff
- No list exposes other districts to district admin
- Audit on staff role/centre/status changes

## 15. Logging/Audit

- audit: centre create/update/status, staff create/update/status/role/centre

## 16. Notifications

- On staff account creation → notify staff with onboarding link (Phase 14 hook)
- On centre inactive — notify district admin (optional)

## 17. Configuration Changes

- Centre code prefix configurable; `centre.closed_blocks_booking=true`

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] Centre CRUD complete with scope
- [ ] Centre status transitions enforced
- [ ] Staff create with role + centre association
- [ ] Role hierarchy restriction enforced
- [ ] District list public
- [ ] Staff detail includes role/centre/permissions

## 20. Testing Checklist

- [ ] Super admin creates centre → 200
- [ ] Duplicate code → 409
- [ ] District admin creates centre in other district → 403
- [ ] Create staff with CENTRE_MANAGER without centre → 400
- [ ] District admin assigns SUPER_ADMIN → 400 ROLE_NOT_ALLOWED
- [ ] Deactivate centre with future bookings → blocked
- [ ] Staff list filtered by district for district admin
- [ ] Farmers can list centres (all active)

## 21. What NOT to Implement

- No slot generation (Phase 09)
- No bookings (Phase 10)
- No queue/procurement/payments yet

---

**Depends on**: Phase 04-07
**Feeds into**: Phase 09+