# Business Rules

Complete list of business rules for the Farmer Procurement & Queue Management System. These are the source of truth for implementation.

## 1. Farmer & Data Access

| ID | Rule |
|----|------|
| BR-01 | Farmers can only see their own data (profile, bookings, queue, procurements, payments, notifications). |
| BR-02 | Staff see only data within their resource scope (Super=all, District=district, Manager/Operator=centre). |
| BR-03 | The Flutter app never connects directly to MySQL. All data via REST API. |
| BR-04 | No frontend application may connect directly to MySQL. |

## 2. Verification & Registration

| ID | Rule |
|----|------|
| BR-05 | Farmer self-registration requires mobile number + OTP verification. |
| BR-06 | Farmer verification workflow: PENDING → APPROVED or REJECTED (reason required). |
| BR-07 | Rejected farmers can be re-reviewed (REJECTED → PENDING → APPROVED/REJECTED). |
| BR-08 | Rejected/approved decisions are not arbitrary toggles — each decision audited, requires permission & reason. |
| BR-09 | Staff cannot self-register. Authorized admin creates staff accounts. |
| BR-10 | Super Admin is created only during initial secure setup (seeder). |
| BR-11 | No user can grant a permission or assign a role higher than their own level. |

## 3. Centres

| ID | Rule |
|----|------|
| BR-12 | Centres have: name, code, district, address, contact, working hours, working days, capacity, manager, operators. |
| BR-13 | Centre code is unique. |
| BR-14 | Centres are never hard-deleted once used. Use ACTIVE/INACTIVE status. |
| BR-15 | Deactivation does not delete existing bookings/history. |
| BR-16 | Only authorized staff can create/modify/deactivate centres. |

## 4. Slots

| ID | Rule |
|----|------|
| BR-17 | A slot belongs to a centre, has a date, start time, end time, capacity. |
| BR-18 | Duplicate slots (same centre + date + time range) are prevented. |
| BR-19 | Invalid dates (past, outside schedule) are prevented. |
| BR-20 | Overbooking is prevented: `booked_count < capacity` enforced transactionally. |
| BR-21 | When a slot reaches capacity, status → FULL; new bookings rejected. |
| BR-22 | On cancellation/expiry, capacity freed if booking was for that slot. |
| BR-23 | Slots can be ACTIVE/INACTIVE/FULL. Deactivated slots reject bookings. |
| BR-24 | Slots are soft-deleted, not hard-deleted (especially if referenced by bookings). |

## 5. Bookings & Tokens

| ID | Rule |
|----|------|
| BR-25 | A farmer must be APPROVED to book (verification_status = APPROVED). |
| BR-26 | A farmer cannot have more than one active booking (per centre/day or overall, configurable). |
| BR-27 | Booking flow: Farmer → Centre → Date → Slot → Confirm → Server validation → Booking → Token → Notification. |
| BR-28 | Booking statuses: PENDING → CONFIRMED → COMPLETED/CANCELLED/EXPIRED. |
| BR-29 | **One booking = one queue token = one queue entry.** |
| BR-30 | **One booking can contain multiple crops (booking_crops).** |
| BR-31 | **Each crop gets its own procurement record (and its own payment).** |
| BR-32 | Token generated server-side (not client). Format: `CENTRE_CODE-DATE-SEQUENCE`. |
| BR-33 | Token is unique and non-sequential/randomized enough to be non-guessable. |
| BR-34 | Booking creation is transactional (check capacity → create booking → crops → token → queue entry → commit/rollback). |
| BR-35 | Cancellation follows policy: within `booking_cancellation_window_minutes` (configurable, default 120 min) before slot. |
| BR-36 | In-work/progress bookings cannot be casually cancelled by farmer. |
| BR-37 | Cancellation requires reason + audit. |
| BR-38 | EXPIRED: booking passes date without farmer arriving. |

## 6. Queue

| ID | Rule |
|----|------|
| BR-39 | Queue statuses: WAITING → CALLED → IN_PROGRESS → COMPLETED. |
| BR-40 | Alternate transitions: CALLED → SKIPPED, WAITING → CANCELLED. |
| BR-41 | Queue order cannot be manually manipulated by operators (only Manager+ can adjust, audited + reason). |
| BR-42 | "Call Next" is concurrency-protected (atomic UPDATE with status guard / SELECT FOR UPDATE). |
| BR-43 | Only the oldest WAITING entry is called next (FIFO by created_at). |
| BR-44 | A farmer entry can only be CALLED once. |
| BR-45 | SKIPPED requires a reason (e.g., farmer not present). Skipped farmers can be re-called (optional policy). |
| BR-46 | Live queue uses AJAX polling (NOT WebSockets). |
| BR-47 | Queue shows: current token, farmers ahead, estimated waiting time. |

## 7. Procurement

| ID | Rule |
|----|------|
| BR-48 | Procurement tracks: farmer, booking, crop, quantity/weight, quality, status, notes, completion time. |
| BR-49 | Procurement statuses: PENDING → VERIFIED → IN_PROGRESS → COMPLETED/REJECTED. |
| BR-50 | Rejection requires a reason. |
| BR-51 | Completed procurement records cannot be casually deleted. |
| BR-52 | **Each booking_crop → one procurement record.** |
| BR-53 | Procurement creation updates queue entry to IN_PROGRESS (if not already). |
| BR-54 | Quality/weight verification required before COMPLETED (VERIFIED step). |
| BR-55 | State transitions are validated; no arbitrary status jumps. |

## 8. Payments

| ID | Rule |
|----|------|
| BR-56 | Payment statuses: PENDING → PROCESSING → PAID/FAILED. |
| BR-57 | Only authorized roles (Manager+) can update sensitive payment states. |
| BR-58 | Each procurement has its own payment record. |
| BR-59 | Payment reference (UTR/cheque/txn) recorded when PAID. |
| BR-60 | Cannot unpay a PAID payment (reversal is District+ and audited). |
| BR-61 | Payment status changes are audited. |
| BR-62 | Farmers see payment status for their own procurements. |

## 9. Approval / Rejection / Reversal

| ID | Rule |
|----|------|
| BR-63 | No arbitrary status toggling. Use: Existing Decision → Re-review → Authorized Review → New Decision. |
| BR-64 | Every approval/rejection decision audited (reason + actor). |
| BR-65 | Sensitive corrections require approval (approve_corrections permission). |
| BR-66 | Use CANCELLED / REJECTED / REVERSED / INACTIVE instead of destructive deletion. |

## 10. Notifications

| ID | Rule |
|----|------|
| BR-67 | Notification triggers: booking confirmed/cancelled, verification approved/rejected, queue approaching, farmer called, procurement completed, payment processing/paid. |
| BR-68 | Prevent duplicate event notifications (idempotency key). |
| BR-69 | Queue notification threshold configurable (`queue_notification_threshold`, default 3). |
| BR-70 | Push notifications via **OneSignal**; OTP/2FA messages via **OTP SMS gateway** (separate service). Keys hardcoded in config for now. |
| BR-71 | Delivery retry: PENDING → ATTEMPT → FAILED → RETRY → SENT; after max attempts → FAILED. |
| BR-72 | Do not retry forever (max attempts configurable, default 3). |
| BR-73 | Notification design supports future channels (e.g., email) without redesign (channel registry). |
| BR-73A | **2FA (OTP)**: optional per user; when enabled, login requires OTP step. OTPs are SMS via OTP gateway, 6-digit, 5 min expiry, max 5 attempts, 60s resend cooldown, rate limited 3/5min. |
| BR-73B | OTP templates (register, login_2fa, password_reset, mobile_change) defined in en + hi; fallback to en. |

## 11. Security & Secrets

| ID | Rule |
|----|------|
| BR-74 | HTTPS enforced. |
| BR-75 | Passwords hashed with argon2id (password_hash). |
| BR-76 | Session/refresh/remember tokens stored as SHA-256 hashes (never plain). |
| BR-77 | Tokens never logged; no secrets in audit/logs. |
| BR-78 | Secrets encrypted at rest (AES-256-GCM); key from environment, never in DB. |
| BR-79 | Secrets masked in UI; complete secret never displayed after save. |
| BR-80 | Secret rotation supported. |
| BR-81 | Prepared statements always; no SQL injection. |
| BR-82 | Input validation + output escaping. |
| BR-83 | CSRF protection for web portal. |
| BR-84 | Rate limiting on login, OTP, SMS, booking, sensitive APIs. |
| BR-85 | File upload security: type/size validation, no arbitrary file execution. |
| BR-86 | No raw errors to users (generic error + logged detail). |

## 12. Sessions & Auth

| ID | Rule |
|----|------|
| BR-87 | Access tokens expire (Flutter 15 min). |
| BR-88 | Refresh tokens expire (7 days) & rotate. |
| BR-89 | Remember-me tokens expire (30 days) & are revocable, separate from auth. |
| BR-90 | Web sessions expire after inactivity (30 min configurable). |
| BR-91 | Logout revokes the session (+ related remember token). |
| BR-92 | Multi-device/multi-session supported; manageable. |
| BR-93 | Admin can revoke sessions. Sensitive session actions audited. |
| BR-94 | Login history tracked (success/failure, reason, IP, device). |

## 13. Language

| ID | Rule |
|----|------|
| BR-95 | Default language: English. Supported: English, Hindi (extensible). |
| BR-96 | Missing translation → fallback to English. |
| BR-97 | Never show blank labels. |
| BR-98 | Super Admin manages languages (add/enable/disable/default/edit/delete). |
| BR-99 | Language delete only if safe (no active users). |

## 14. File Management

| ID | Rule |
|----|------|
| BR-100 | Files referenced by stable ID (files table), not raw paths. |
| BR-101 | Move/rename detects references → asks "Fix Locations?" → updates references → validates. |
| BR-102 | No broken images after internal file movement. |
| BR-103 | External URL failure → fallback image. |
| BR-104 | Files soft-deleted; not hard-deleted when referenced. |
| BR-105 | File storage abstraction for future object-storage migration. |

## 15. Maintenance Mode

| ID | Rule |
|----|------|
| BR-106 | Maintenance mode is admin (Super Admin) controlled, stored in system settings. |
| BR-107 | When ON: Farmer app shows maintenance screen; public APIs return 503. |
| BR-108 | Health/status endpoints remain available during maintenance. |
| BR-109 | Authorized admins (Super Admin) can bypass maintenance. |
| BR-110 | Maintenance screen shows message, expected availability, support info. |
| BR-111 | Enable/disable audited. |

## 16. System Settings & Configuration

| ID | Rule |
|----|------|
| BR-112 | Business parameters configurable, not hardcoded: cancellation window, queue threshold, capacity, working hours, rate limits, SMS settings. |
| BR-113 | Sensitive values go through secrets manager; normal config stored normally. |
| BR-114 | Critical configuration changes audited. |

## 17. Audit & Logging

| ID | Rule |
|----|------|
| BR-115 | Every important action auditable (login, permission change, staff creation, centre/slot modification, booking cancel, queue correction, procurement completion, payment change, approval/rejection/reversal, file ops, language change, secret update, maintenance change, setting change). |
| BR-116 | Audit log access scoped (Farmer=none, Operator=none/self, Manager=centre, District=district, Super=all). |
| BR-117 | Logs separate: application, api, security, notification, audit. |
| BR-118 | Never log passwords, OTPs, tokens, secrets, keys, excessive PII. |

## 18. Transactions & Concurrency

| ID | Rule |
|----|------|
| BR-119 | Booking creation is transactional. |
| BR-120 | Call Next concurrency-protected. |
| BR-121 | Procurement state transitions consistent. |
| BR-122 | Slot capacity enforcement at database layer. |
| BR-123 | Duplicate payment update prevented. |
| BR-124 | Duplicate notification trigger prevented. |
| BR-125 | Backend, not frontend, enforces consistency. |

## 19. Pagination

| ID | Rule |
|----|------|
| BR-126 | All large lists paginated: farmers, bookings, queue history, procurements, payments, notifications, audit logs, login history, files. |
| BR-127 | Default per_page 20, max 100. |

## 20. Backup & Recovery

| ID | Rule |
|----|------|
| BR-128 | Database + file backups scheduled. |
| BR-129 | Restore procedure documented (not just backup command). |
| BR-130 | Backup verification (test restore). |
| BR-131 | Retention policy documented. |

## 21. Cron Jobs

| ID | Rule |
|----|------|
| BR-132 | Scheduled jobs: expire sessions, clean remember tokens, retry notifications, cleanup temp files, rotate logs, reports, backup. |
| BR-133 | Each job: frequency, purpose, command, failure behavior, logging documented. |

## 22. UI/UX

| ID | Rule |
|----|------|
| BR-134 | Theme: minimal + professional + light green agricultural. |
| BR-135 | Farmer app prioritizes usability; admin portal prioritizes information density. |
| BR-136 | Status badges clear; limited animation; no excessive gradients. |

## 23. Support

| ID | Rule |
|----|------|
| BR-137 | Farmers can create support requests; staff can respond. |
| BR-138 | Support requests tracked with status (OPEN → IN_PROGRESS → RESOLVED/CLOSED). |

## Key Configurable Parameters (Business)

| Parameter | Default | Used In |
|-----------|---------|---------|
| `booking_cancellation_window_minutes` | 120 | BR-35 |
| `queue_notification_threshold` | 3 | BR-69 |
| `slot capacity` | per-slot | BR-20 |
| `daily_capacity` | per-centre | BR-12 |
| `working_hours/days` | per-centre | BR-12 |
| `sms_enabled` | true | BR-70 |
| `push_enabled` | true | BR-70 |
| `otp_expiry_minutes` | 5 | BR-73A |
| `otp_resend_cooldown_seconds` | 60 | BR-73A |
| `two_factor_enabled_default` | false | BR-73A |
| `notification_retry_count` | 3 | BR-72 |
| `session_timeout_minutes` | 30 | BR-90 |
| `remember_me_expiry_days` | 30 | BR-89 |
| `max_concurrent_sessions` | 10 | BR-92 |
| `rate limits` | multiple | BR-84 |

---

**Next**: [16-booking-workflow.md](16-booking-workflow.md) for booking workflow.