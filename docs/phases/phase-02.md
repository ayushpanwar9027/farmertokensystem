# Phase 02 — Database Schema & Migrations

## 1. Objective

Create the full MySQL schema (all tables from [09-database-schema.md](09-database-schema.md)) with a custom migration runner and seeders for baseline data (roles, permissions, languages, settings).

## 2. Prerequisites

- Phase 01 foundation (bootstrap, config, migrate console)
- Phase 00 business rules

## 3. Features

- Migration runner (`app/console/migrate.php`)
- All tables: users, roles, permissions, role_permissions, user_permissions, user_sessions, remember_tokens, user_devices, login_history, farmers, districts, procurement_centres, centre_staff, slots, bookings, booking_crops, tokens, queue_entries, procurements, payments, notifications, notification_logs, languages, translations, files, file_folders, file_references, system_settings, system_secrets, audit_logs, error_logs, support_requests, otp_verifications, migrations
- Indexes per [08-database-architecture.md] + [09-database-schema.md]
- Seeder (`app/console/seed.php`): roles, permissions, role_permissions, languages (en/hi), system_settings defaults, system_secrets placeholders, sample districts
- Demo seeder stub (`--demo`) for Phase 17

## 4. Files to Create

```
app/console/migrate.php
app/console/seed.php
database/migrations/*.php          (one per table or grouped by module)
database/seeders/roles_seeder.php
database/seeders/permissions_seeder.php
database/seeders/role_permissions_seeder.php
database/seeders/language_seeder.php
database/seeders/settings_seeder.php
database/seeders/secrets_seeder.php
database/seeders/district_seeder.php
```

## 5. Files to Modify

- `config/config.php` (migration path config if needed)

## 6. Database Changes

- Create `farmer_procurement` database (utf8mb4)
- All tables (as defined in 09-database-schema.md)
- Seed baseline data

## 7. API Changes

- None (Phase 03+)

## 8. Backend Logic

- Migration runner reads `database/migrations`, applies in order, records in `migrations` table, supports `--status`, `--rollback`
- Seeder is idempotent (INSERT OR IGNORE / check before insert)

## 9. Flutter Changes

- None

## 10. Staff/Admin Changes

- None

## 11. Permissions

- Seed all permissions from [14-permission-matrix.md]
- Seed role_permissions defaults

## 12. Validation

- Migrations run in transactional batches where possible
- Seeder never duplicates data

## 13. Error Handling

- Migration failures logged + rollback partial batch
- Clear CLI error messages

## 14. Security

- DB credentials only from `.env`
- Seeder Super Admin password generated randomly + printed once (must be changed)

## 15. Logging/Audit

- Migration/seed runs logged to application.log

## 16. Notifications

- None

## 17. Configuration Changes

- `DB_*` values in `.env` used by Database config

## 18. Dependencies

- None new (PDO MySQL)

## 19. Completion Criteria

- [ ] `php app/console/migrate.php` runs clean on fresh DB
- [ ] Re-run is no-op (status shows applied)
- [ ] Seeder populates roles/permissions/languages/settings/districts
- [ ] `SHOW TABLES` matches schema doc (plus migrations)
- [ ] Indexes verified

## 20. Testing Checklist

- [ ] Fresh DB migrate → clean
- [ ] Re-migrate → no re-runs
- [ ] Seed → counts match expectations (5 roles, ~28 permissions, 2 languages)
- [ ] Foreign keys enforced (insert invalid → fails)
- [ ] utf8mb4: insert Hindi text → round-trips

## 21. What NOT to Implement

- No auth logic
- No CRUD services
- No API endpoints
- Don't seed sensitive real data (only placeholders for secrets)

---

**Depends on**: Phase 01
**Feeds into**: Phase 03+