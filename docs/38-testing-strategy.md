# Testing Strategy

## Overview

Pragmatic testing approach for a beginner team without a heavy CI stack. Manual + scripted + sanity checks. Documented test cases per feature.

## Testing Levels

1. **Manual API testing** (Postman/cURL) — endpoint by endpoint
2. **PHPUnit-style unit tests** (optional, PHPUnit or simple test scripts) — models/services
3. **Flutter widget/unit tests** — core logic + screens
4. **End-to-end sanity** — full flows in a staging copy
5. **Security checks** — auth/scope/injection
6. **Performance sanity** — pagination, index usage, polling load

## Test Environments

| Env | Purpose | DB | Data |
|-----|---------|----|----|
| Local (XAMPP) | Fast iteration | Local MySQL | Demo seed |
| Staging (hosting subdomain) | Integration | Separate DB | Demo seed |
| Production | Final | Real | Real |

Keep seeding script to populate demo data (centres, slots, farmers, bookings, queue, procurements).

## API Testing (Primary)

Use Postman collection or cURL scripts. For each endpoint test matrix:

- Success (200/201)
- Validation (400)
- Unauthenticated (401)
- Forbidden role (403)
- Not found (404)
- Conflict (409)
- Rate limited (429)
- Maintenance (503)
- Pagination
- Filters
- Sorting
- Localization header

### Auth tests
- Registration + OTP verify + complete
- Login success/failure/locked
- **2FA: login returns 202 + TWO_FA_REQUIRED; verify-2fa success/invalid/expired**
- **2FA: enable → confirm → login now requires OTP; disable requires OTP; step-up challenge/confirm**
- **OTP resend cooldown + rate limit (3/5min)**
- Token expiry
- Refresh rotation
- Logout revokes
- Remember-me
- Multi-device sessions
- Session revoke
- Password change/reset

### Notification tests
- Booking confirmed → in-app + OneSignal push (player_id from device)
- Queue approaching triggers once (dedup) at threshold
- Push failure → PENDING → retry → FAILED after max attempts
- Test push endpoint delivers to device
- OTP SMS sends via gateway; never logged

### Booking tests
- Valid booking
- Slot full rejection
- Duplicate active booking rejection
- Inactive/past slot rejection
- Cancellation window
- Crop validations (multi-crop)
- Token generation uniqueness
- Concurrent booking (parallel calls)

### Queue tests
- Call next success
- Empty queue
- Concurrent call-next (two parallel requests → one success)
- Skip with/without reason
- State transition guards
- Live queue polling data correctness

### Procurement tests
- Create multi-crop procurements for booking
- Verify/start/complete transitions
- Reject requires reason
- Completed non-deletable
- Payment creation per procurement

### Payment tests
- Status transitions
- Unauthorized update denied (operator)
- Duplicate PAID prevention
- Reference required on PAID

### Admin tests
- Staff creation + permission overrides
- Permission denial
- Scope denial (district admin accessing other district)
- Audit log entries for key actions
- Settings/secrets update + masks
- Maintenance toggle + bypass
- Language add/translations/fallback
- File upload validation + move references

## Concurrency Testing

- **Slot capacity**: fire N parallel booking requests for last remaining slot → exactly one succeeds
- **Call Next**: fire 2 parallel → 1 succeeds, 1 CONCURRENT_UPDATE
- **Duplicate payment**: parallel PAID updates → single transaction
- **Notification dedup**: repeated event → single send

## Flutter Testing

- Widget tests: login screen, centre list, slot selection, queue screen states (loading/error/empty/data)
- Service test: API client parsing, token refresh handling
- SecureStorage mock tests
- Localization fallback test (missing key → English)

## Performance Sanity

- Validate queries use indexes (EXPLAIN on hotspots)
- Pagination limits respected
- Queue polling endpoint latency < 200ms
- Booking endpoint < 500ms
- Load test (simple): 100 concurrent booking/queue calls via a script; note DB limits on shared hosting

## Security Testing Checklist

- [ ] SQLi attempts on search/filter/id params
- [ ] XSS payloads in name/notes fields
- [ ] CSRF missing token rejected (web)
- [ ] Unauthorized direct file access (./../ traversal)
- [ ] Upload of .php/.sh/.exe rejected
- [ ] Oversized upload rejected
- [ ] JWT tampering rejected
- [ ] Refresh token replay after rotation rejected
- [ ] Role escalation attempt (self role change) rejected
- [ ] Scope bypass (access other district ID) rejected
- [ ] Secrets masked; not leaked in logs
- [ ] No raw errors in production responses
- [ ] Rate limits enforced
- [ ] Maintenance mode blocks public, allows Super Admin

## Test Data Seeder

`php app/console/seed.php --demo`
Creates: Super Admin, District Admin, Managers, Operators, a few farmers (approved/rejected), centres, slots for today/tomorrow, sample bookings, queue states, procurements, payments.

## Regression Approach

- After each phase, run the phase test checklist
- Keep a `docs/38-testing-strategy.md` checklist (this) + phase test checklists in phase files
- Update PROJECT-STATE with test results

## Bug Tracking

- Known bugs tracked in PROJECT-STATE.md
- Each bug: repro steps (brief), expected/actual, fix phase

---

**Next**: [39-deployment.md](39-deployment.md) for deployment.