# Detailed Requirements

## Functional Requirements

### FR-01: Farmer Registration & Verification
- Farmer registers with mobile number
- OTP verification via SMS
- Basic details: name, address, district, village, farm size, crops
- Account creation after verification
- Verification workflow: PENDING → APPROVED/REJECTED
- Re-review capability for rejected farmers
- Login with mobile + password

### FR-02: Centre Management
- Create/edit/deactivate centres (no hard delete)
- Centre details: name, code, address, district, contact, working hours/days, capacity
- Manager assignment
- Operator assignment
- District mapping
- Active/inactive status

### FR-03: Slot Management
- Create slots: date, start time, end time, capacity, centre
- Status: ACTIVE, INACTIVE, FULL
- Prevent overbooking
- Prevent duplicate slots (same centre/date/time)
- Capacity enforcement at database level

### FR-04: Slot Booking
- Farmer selects centre → date → available slot → confirm
- Server-side validation: capacity, duplicate active booking, slot status
- Transactional booking creation
- Token generation (server-side, unique)
- Queue entry creation
- Status: PENDING → CONFIRMED → COMPLETED/CANCELLED/EXPIRED
- SMS confirmation

### FR-05: Digital Token
- Unique token per booking
- Format: CENTRE_CODE-DATE-SEQUENCE (e.g., APC-20260908-0042)
- Display in app with QR code
- Non-sequential, non-guessable

### FR-06: Live Queue
- Real-time queue view for farmer and staff
- AJAX polling (5-10 second intervals)
- Queue positions: farmers ahead, estimated wait
- Statuses: WAITING → CALLED → IN_PROGRESS → COMPLETED
- Alternate: CALLED → SKIPPED, WAITING → CANCELLED
- Current token display
- Concurrency protection for "Call Next"

### FR-07: Queue Operations (Staff)
- Call Next (atomic, concurrency-safe)
- Mark Arrived
- Start Procurement
- Complete Procurement
- Skip (with reason)
- Manual queue position adjustment (Manager+ only, audited)

### FR-08: Procurement Management
- One booking = one queue token
- One booking = multiple crops
- Each crop = separate procurement record
- Track: crop, variety, quantity (kg), quality grade, moisture %, notes
- Statuses: PENDING → VERIFIED → IN_PROGRESS → COMPLETED/REJECTED
- Rejection requires reason
- Completion timestamp
- Soft delete only

### FR-09: Payment Status
- Track per procurement record
- Statuses: PENDING → PROCESSING → PAID/FAILED
- References: UTR, transaction ID, cheque no
- Only authorized roles can update
- SMS on status change

### FR-10: Notifications (Push + In-App, OneSignal)
Triggers:
- Booking confirmed
- Booking cancelled
- Verification approved/rejected
- Queue approaching (configurable threshold)
- Farmer called
- Procurement completed
- Payment processing/paid
- Deduplication: prevent duplicate event notifications
- **2FA OTP SMS** handled via OTP gateway (separate from notifications), with templates

### FR-11: Staff Management
- No public registration
- Admin creates: Centre Operator, Centre Manager, District Admin
- Super Admin created at setup
- Role assignment with default permissions
- Custom permission overrides
- Lower roles cannot grant higher permissions

### FR-12: Role-Based Access Control
- Roles: Super Admin, District Admin, Centre Manager, Centre Operator, Farmer
- Permissions: granular checkboxes
- Resource scoping: all/assigned district/assigned centre/own data
- Backend enforcement on every request

### FR-13: Authentication & Sessions
- Web: Session cookies + CSRF
- Flutter: JWT access + refresh tokens
- Remember-me tokens (optional, long-lived, revocable)
- Device/session tracking
- Login history
- Session revocation UI (Super Admin)
- Secure token storage (hashed in DB)
- **2FA (OTP)**: optional per-user; login 2-step password→OTP; enable/disable/step-up flows

### FR-14: System Settings & Secrets
- Super Admin: System Settings (non-sensitive config)
- Super Admin: Secrets Manager (OneSignal/OTP keys planned; hardcoded in config for now)
- Encrypted storage for secrets
- Masked UI for secrets
- Bootstrap mechanism for encryption key
- Maintenance mode toggle (Super Admin)

### FR-15: Language Management
- Default: English
- Supported: English, Hindi
- Super Admin: add/enable/disable/set default/edit/delete languages
- Translation keys with English fallback
- No blank labels

### FR-16: File Manager
- Super Admin only
- Upload, URL add, folders, rename, move, copy, paste, search, preview, delete
- Stable file references (not raw paths)
- Reference update on move/rename
- External URL fallback

### FR-17: Audit Logging
- Every important action logged
- Fields: user, action, module, entity, old/new values, reason, IP, UA, request ID, timestamp
- Scope-based access: Farmer (none), Operator (self), Manager (centre), District (district), Super (all)
- Filters: date, user, role, action, module, entity, centre, district

### FR-18: Error Handling & Logging
- Separate logs: application, api, security, notification
- Structured logging with request ID
- No sensitive data in logs
- Internal error tracking (count, endpoint, frequency, severity)

### FR-19: Rate Limiting
- Login, OTP, SMS, booking, sensitive APIs
- Shared hosting compatible (file/DB based)
- Brute force, SMS abuse, booking spam prevention

### FR-20: Backup & Recovery
- Database backups (cron)
- File backups
- Retention policy
- Documented restore procedure
- Backup verification

### FR-21: Cron Jobs
- Expire sessions
- Clean remember tokens
- Retry failed notifications
- Cleanup temp files
- Rotate logs
- Generate reports
- Database backup

## Non-Functional Requirements

### Performance
- API response < 500ms (p95)
- Queue polling < 200ms
- Support 1000+ concurrent farmers
- Pagination on all lists (default 20, max 100)

### Security
- HTTPS enforced
- Password hashing (bcrypt/argon2)
- Prepared statements
- Input validation + output escaping
- CSRF protection (web)
- Secure cookies (HttpOnly, Secure, SameSite)
- Token expiration & revocation
- Encrypted secrets
- Security headers (CSP, HSTS, X-Frame-Options)
- No raw errors to users
- Audit all sensitive actions

### Reliability
- Transactional integrity for booking/queue/procurement
- Idempotent notification sending
- Graceful degradation (maintenance mode)
- Health check endpoint

### Usability
- Light green agricultural theme
- Minimal, professional UI
- Clear status badges
- Empty states, loading states, error states
- Offline indicator (Flutter)
- Accessible contrast ratios

### Maintainability
- Modular PHP structure
- Clear separation of concerns
- Documented APIs
- Consistent naming conventions
- Configuration over hardcoding

### Scalability (Future)
- Stateless API design
- Database indexing strategy
- File storage abstraction
- Notification abstraction
- Ready for VPS/object storage migration

## Business Rules Summary

See [15-business-rules.md](15-business-rules.md) for complete list.

Key rules:
- Farmer sees only own data
- Staff sees only scoped data
- Slot capacity never exceeded
- No duplicate active bookings
- Token generated server-side
- Queue order protected (concurrency)
- Procurement requires verification
- Payment updates restricted
- Rejection requires reason
- Corrections require approval/audit
- No hard deletes for audit records
- Translation fallback to English
- Image fallback to default
- File moves preserve references
- Secrets never in logs
- Sessions expire
- Remember-me expires/revokes
- Maintenance mode admin-controlled
- Critical changes audited

## Configurable Parameters

| Parameter | Default | Configurable By |
|-----------|---------|-----------------|
| Booking cancellation window | 2 hours | Super Admin |
| Queue notification threshold | 3 farmers | Super Admin |
| Default slot capacity | 50 | Super Admin |
| SMS max retries | 3 | Super Admin |
| Session timeout (web) | 30 min | Super Admin |
| Session timeout (app) | 7 days | Super Admin |
| Remember-me expiry | 30 days | Super Admin |
| File upload max size | 5 MB | Super Admin |
| Rate limit: login | 5/min | Super Admin |
| Rate limit: OTP | 3/5min | Super Admin |
| Rate limit: booking | 10/min | Super Admin |
| Maintenance message | "System under maintenance" | Super Admin |
| Support contact | - | Super Admin |
| System name | "Farmer Procurement System" | Super Admin |

## Assumptions & Constraints

- Shared hosting (no Redis, no root access)
- Manual deployment (no CI/CD)
- No Git workflow required
- Team: beginner students
- SIH hackathon timeline
- Android-only farmer app
- Push notifications via OneSignal; OTP via SMS gateway (keys hardcoded for now)
- MySQL 8.0+
- PHP 8.1+
- Apache with mod_rewrite