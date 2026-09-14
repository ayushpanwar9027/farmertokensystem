# Database Architecture

## Overview

MySQL 8.0+ relational database with InnoDB storage engine. Focus on data integrity, transaction support, and proper indexing. utf8mb4 charset for full Unicode (incl. Devanagari for Hindi).

## Design Principles

1. **ACID transactions** - Critical operations atomic (booking, queue, procurement)
2. **Prepared statements everywhere** - No string concatenation for SQL
3. **Soft deletes** - No destructive deletes for audited/important records
4. **Status enums** - VARCHAR with allowed ENUM constraints (MySQL ENUM where simple)
5. **Timestamp columns** - All tables have `created_at`, `updated_at`
6. **Indexes on query paths** - Every frequent query has supporting index
7. **Foreign keys** - Where meaningful (with ON DELETE RESTRICT or SET NULL)
8. **No unnecessary tables** - Keep schema lean (per SIH requirements)

## Connection Configuration

.env:
```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=farmer_procurement
DB_USERNAME=fps_user
DB_PASSWORD=secure_password
DB_CHARSET=utf8mb4
```

PDO settings:
```php
[
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
  PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]
```

## Database Creation

```sql
CREATE DATABASE IF NOT EXISTS farmer_procurement
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

All tables use:
```sql
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
```

## Table Organization (Modules)

```
├── auth/                    # Users, roles, sessions, tokens, devices
├── farmer/                  # Farmers, farmer verification
├── centre/                  # Centres, centre staff
├── slot/                    # Slots
├── booking/                 # Bookings, booking crops, tokens
├── queue/                   # Queue entries
├── procurement/             # Procurements, payments
├── notification/            # Notifications, notification logs
├── language/                # Languages, translations
├── file/                    # Files, folders, references
├── config/                  # Settings, secrets
├── audit/                   # Audit logs
├── error/                   # Error logs
├── support/                 # Support requests
└── session/                 # Sessions, remember tokens, login history
```

## Full Table List

### 1. users
Core user accounts (farmers + staff + admins)

### 2. roles
Role definitions

### 3. permissions
Permission definitions

### 4. role_permissions
Role↔Permission many-to-many

### 5. user_permissions
User↔Permission overrides

### 6. user_sessions
Active sessions

### 7. remember_tokens
Remember-me tokens

### 8. user_devices
Device tracking

### 9. login_history
Login/logout/failure tracking

### 10. farmers
Farmer-specific profile

### 11. districts
Administrative districts

### 12. procurement_centres
Procurement centres

### 13. centre_staff
Centre↔staff assignments

### 14. slots
Time slots

### 15. bookings
Booking records

### 16. booking_crops
Crops within a booking

### 17. tokens
Digital tokens per booking

### 18. queue_entries
Queue positions

### 19. procurements
Procurement records (per booking crop)

### 20. payments
Payment records (per procurement)

### 21. notifications
Farmer notifications

### 22. notification_logs
Push (OneSignal) + OTP SMS delivery logs

### 23. languages
Language definitions

### 24. translations
Translation key/value

### 25. files
File metadata

### 26. file_folders
Folder hierarchy

### 27. file_references
Where files are referenced

### 28. system_settings
Non-sensitive configuration

### 29. system_secrets
Encrypted sensitive configuration

### 30. audit_logs
Audit trail

### 31. error_logs
Application error tracking

### 32. support_requests
Farmer support tickets

### 33. otp_verifications
OTP for registration/reset (namespaced for auth module)

### 34. maintenance_events
Maintenance mode history (optional, or in settings + audit)

## Conventions

### Primary Keys
- `id` INT UNSIGNED AUTO_INCREMENT for all tables
- Or `BIGINT UNSIGNED` where join volume could exceed INT range (audit, error logs)

### Timestamp Columns
Every table:
```sql
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```

### Soft Delete
Tables with soft delete add:
```sql
deleted_at DATETIME NULL DEFAULT NULL
```
Indexed for active filtering. All queries filter `deleted_at IS NULL` unless explicitly viewing history.

### Status Columns
- Use ENUM where set is fixed and small
- Use VARCHAR with CHECK constraints where set may grow
- Example: `status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'`

### Booleans
- `TINYINT(1)` with values 0/1
- Named `is_*` or `has_*` for clarity

### Money
- DECIMAL(12,2) for amounts
- Never FLOAT/DOUBLE for money

### Weights
- DECIMAL(10,3) for kilograms (3 decimal precision)

## Indexing Strategy

### Every important index + rationale:

| Table | Column(s) | Type | Rationale |
|-------|-----------|------|-----------|
| users | `mobile` | UNIQUE | Fast login lookup, uniqueness constraint |
| users | `email` | UNIQUE | Optional email lookup |
| users | `username` | UNIQUE | Staff login |
| users | `role_id` | INDEX | Role filtering |
| users | `status` | INDEX | Active user queries |
| user_sessions | `user_id` | INDEX | List user's sessions |
| user_sessions | `token_hash` | UNIQUE | Session validation lookup |
| user_sessions | `expires_at` | INDEX | Cron expiration cleanup |
| remember_tokens | `user_id` | INDEX | List user tokens |
| remember_tokens | `token_hash` | UNIQUE | Token validation |
| remember_tokens | `expires_at` | INDEX | Cleanup |
| login_history | `user_id` | INDEX | User history |
| login_history | `login_at` | INDEX | Date-range queries |
| login_history | `status` | INDEX | Success/failure filtering |
| farmers | `user_id` | UNIQUE | One farmer per user |
| farmers | `district_id` | INDEX | District scope filtering |
| farmers | `verification_status` | INDEX | Pending verification queries |
| procurement_centres | `code` | UNIQUE | Centre code lookup |
| procurement_centres | `district_id` | INDEX | District filtering |
| procurement_centres | `status` | INDEX | Active centres |
| centre_staff | `centre_id` | INDEX | Centre staff list |
| centre_staff | `user_id` | UNIQUE | One role assignment per user (or allow multiple) |
| slots | `centre_id` | INDEX | Centre slots |
| slots | `date` | INDEX | Date filtering |
| slots | `(centre_id, date)` | COMPOSITE | Primary schedule lookup |
| bookings | `user_id` | INDEX | Farmer bookings |
| bookings | `booking_number` | UNIQUE | Booking reference lookup |
| bookings | `slot_id` | INDEX | Slot bookings |
| bookings | `status` | INDEX | Status filtering |
| bookings | `(centre_id, date)` | COMPOSITE | Date+centre queue queries |
| booking_crops | `booking_id` | INDEX | Crops per booking |
| tokens | `booking_id` | UNIQUE | One token per booking |
| tokens | `token_number` | UNIQUE | Token lookup |
| queue_entries | `booking_id` | UNIQUE | One entry per booking |
| queue_entries | `(centre_id, date)` | COMPOSITE | Daily queue lookup |
| queue_entries | `status` | INDEX | Waiting/called filtering |
| queue_entries | `created_at` | INDEX | Position ordering |
| procurements | `booking_id` | INDEX | Multi-crop per booking |
| procurements | `(booking_id, crop_name)` | COMPOSITE | Unique crop check |
| procurements | `status` | INDEX | Status filtering |
| payments | `procurement_id` | UNIQUE | One payment per procurement |
| payments | `status` | INDEX | Status filtering |
| payments | `(centre_id, date)` | COMPOSITE | Daily payment queries |
| notifications | `user_id` | INDEX | Farmer notifications |
| notifications | `(user_id, is_read)` | COMPOSITE | Unread badge count |
| notification_logs | `notification_id` | INDEX | Delivery log |
| notification_logs | `status` | INDEX | Retry queries |
| translations | `(language_id, key)` | UNIQUE | Translation lookup |
| files | `folder_id` | INDEX | Folder contents |
| files | `source_type` | INDEX | Type filtering |
| file_references | `file_id` | INDEX | References for rename safety |
| audit_logs | `(created_at)` | INDEX | Time-range queries |
| audit_logs | `user_id` | INDEX | User audit trail |
| audit_logs | `action` | INDEX | Action filtering |
| audit_logs | `module` | INDEX | Module filtering |
| audit_logs | `entity_type` | INDEX | Entity filtering |
| error_logs | `created_at` | INDEX | Date queries |
| error_logs | `endpoint` | INDEX | Endpoint error counts |

## Foreign Keys Summary

```sql
-- users.role_id → roles.id (RESTRICT)
-- farmers.user_id → users.id (CASCADE on user delete)
-- farmers.district_id → districts.id (RESTRICT)
-- procurement_centres.district_id → districts.id (RESTRICT)
-- centre_staff.centre_id → procurement_centres.id (CASCADE)
-- centre_staff.user_id → users.id (CASCADE)
-- slots.centre_id → procurement_centres.id (CASCADE)
-- bookings.user_id → users.id (CASCADE)
-- bookings.centre_id → procurement_centres.id (RESTRICT)
-- bookings.slot_id → slots.id (RESTRICT)
-- tokens.booking_id → bookings.id (CASCADE)
-- queue_entries.booking_id → bookings.id (CASCADE)
-- queue_entries.centre_id → procurement_centres.id (RESTRICT)
-- procurements.booking_id → bookings.id (CASCADE)
-- payments.procurement_id → procurements.id (CASCADE)
-- payments.centre_id → procurement_centres.id (RESTRICT)
-- notifications.user_id → users.id (CASCADE)
-- languages.id → translations.language_id (CASCADE)
-- files.folder_id → file_folders.id (CASCADE)
-- file_references.file_id → files.id (CASCADE)
-- audit_logs.user_id → users.id (SET NULL, graceful)
```

## Transactions

### Booking (atomic)
```sql
START TRANSACTION;
-- 1. Lock slot (SELECT ... FOR UPDATE)
-- 2. Check capacity: booked < capacity
-- 3. Check no duplicate active booking
-- 4. INSERT bookings
-- 5. INSERT booking_crops (multiple)
-- 6. INSERT tokens
-- 7. INSERT queue_entries
COMMIT;  -- or ROLLBACK on any failure
```

### Queue Call-Next (concurrency-safe)
```sql
START TRANSACTION;
-- 1. SELECT oldest WAITING queue_entry
--    WHERE centre_id=? AND date=? 
--    FOR UPDATE  -- locks the row
-- 2. Verify another operator hasn't already called it
-- 3. UPDATE status='CALLED', called_at=NOW(), called_by=?
COMMIT;
```
The `SELECT ... FOR UPDATE` prevents two operators calling the same entry.

Alternative for shared-hosting constraints:
```sql
-- Use optimistic locking:
SELECT ... WHERE status='WAITING' ORDER BY created_at LIMIT 1
-- Then atomic UPDATE with guard:
UPDATE queue_entries 
SET status='CALLED', called_at=NOW(), called_by=? 
WHERE id=? AND status='WAITING'
-- Check affected rows == 1; if 0, someone else took it
```

### Procurement (multi-crop, atomic)
```sql
START TRANSACTION;
-- 1. Verify booking status (CONFIRMED or IN_PROGRESS)
-- 2. For each crop: INSERT procurements (status=PENDING)
-- 3. UPDATE queue_entries status→IN_PROGRESS (if not already)
-- 4. Generate payment placeholders (optional)
COMMIT;
```

## Data Volume Considerations (Shared Hosting)

- Shared hosting MySQL limited (typically 1-2GB database)
- Use indexes aggressively on query paths
- Avoid SELECT * in production queries
- Use LIMIT always
- Archive old audit logs (move to archive table monthly, configurable)
- Notification logs archived/cleaned periodically
- Daily backups essential

## Migrations

Custom PHP migration runner (Phase 02):

```bash
php app/console/migrate.php                  # Run pending migrations
php app/console/migrate.php --rollback       # Rollback last
php app/console/migrate.php --status         # Migration status
```

Migration file format:
```php
// database/migrations/20260908000001_create_users_table.php
return [
    'up' => function ($db) {
        $db->exec("CREATE TABLE users (...)");
    },
    'down' => function ($db) {
        $db->exec("DROP TABLE users");
    }
];
```

Migrations run in order (by timestamp in filename). Each is recorded in `migrations` table.

## Seeders

```bash
php app/console/seed.php                     # Seed roles/permissions/languages
php app/console/seed.php --demo              # Demo data for testing
```

Seed data:
- Default roles (Super Admin, District Admin, Centre Manager, Centre Operator, Farmer)
- Permissions list
- Initial languages (en, hi)
- Default settings

## Backups

```bash
# Daily backup (cron)
mysqldump -u fps_user -p... farmer_procurement | gzip > /home/user/backups/db_$(date +%Y%m%d).sql.gz

# Verify
gzip -t /home/user/backups/db_20260908.sql.gz
```

---

**Next**: [09-database-schema.md](09-database-schema.md) for full schema definitions.