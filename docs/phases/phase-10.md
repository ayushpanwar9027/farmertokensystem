# Phase 10 — Bookings + Tokens (Multi-Crop, One Booking = One Token)

## 1. Objective

Implement the booking feature with the multi-crop rule:
- One booking → **ONE queue token → ONE queue entry**
- One booking → **multiple crop lines** (booking_crops) → later one procurement record per crop → one payment per procurement

## 2. Prerequisites

- Phase 05 (RBAC scope), Phase 08 (centres), Phase 09 (slots)
- Farmer profile readiness (Phase 15 UI, API this phase)

## 3. Features

- Farmer booking: select centre → date → slot → crops (qty, expected weight) → confirm
- Rules enforced: booking window (up to N days; min X hours before slot), one active booking at a time, slot capacity, no centre>district mismatches
- Booking statuses: PENDING → CONFIRMED (on payment? or auto) — decision: **CONFIRMED on successful payment or by rule**; CANCELLED, EXPIRED, REVERSED
- Token auto-generated at booking create (or at arrival) — design: token generated on **CONFIRMED** booking
- Cancellation with window + refund decision config
- Booking list/detail/filter for app + portal
- Payment link shown; unconfirmed PENDING expires via cron (Phase 12/17 wiring)

### Multi-crop record rules
- `bookings.crops = booking_crops[]`
- candidates (farmers must pick existing crop catalog), qty per crop, total expected weight optional
- later procurement record created **per crop**
- Each crop retains its own payment later

## 4. Files to Create

```
app/Controllers/BookingController.php
app/Controllers/Admin/BookingAdminController.php
app/Services/BookingService.php
app/Services/BookingCropService.php
app/Services/TokenService (booking tokens; unique per booking)
app/Models/Booking.php
app/Models/BookingCrop.php
app/Models/Token.php
app/Models/Crop.php (seed crops list)
app/Validators/BookingValidator.php
database/seeders/crop_seeder.php
```

## 5. Files to Modify

- `app/Console/cron.php` (expire-pending + expire-unarrived bookings jobs)
- `config/routes.php` (`/bookings`, `/tokens`)

## 6. Database Changes

- Uses bookings, booking_crops, tokens + slots + centres (Phase 02)
- Ensure unique booking index: `unique(centre_id, date, slot_id, farmer_id)` for active

## 7. API Changes ([07-api-reference.md])

- `GET /bookings?status=&date_from=&date_to=&page=`
- `POST /bookings` (create; validate all rules; generate token when CONFIRMED)
- `GET /bookings/{id}`
- `POST /bookings/{id}/cancel`
- `GET /bookings/{id}/crops`
- `GET /my/token` (current active token for app)
- `GET /admin/bookings?centre_id=&status=&date=&q=` (scoped)
- `GET /admin/bookings/{id}`
- `POST /admin/bookings/{id}/cancel` (admin)
- `POST /admin/bookings/{id}/reschedule` (optional/bonus — mark not-implemented default)

## 8. Backend Logic

BookingService.create:
1. Validate farmer role + status (APPROVED farmer)
2. Validate centre ACTIVE + district ok
3. Validate slot: exists, available, in future ≥ min_lead_hours, date within horizon
4. Validate one-active-booking rule (extra: not for cancelled ones)
5. Validate capacity: `booked_count < capacity`; use atomic `UPDATE slots SET ... WHERE < capacity` guard to prevent oversell; re-query on conflict
6. Validate crops exist + qty ranges; compute rough total weight if QC rule applicable
7. Insert booking (PENDING) + booking_crops rows (transaction)
8. Insert token rows? — design: token issued only on CONFIRMED (status transition) → generate token_number (e.g., `GKP-2026-00123`), SHA-256 hash for lookup; store plaintext only for display (or base32 randomness)
9. If auto-confirm rule (payment required first) → leave PENDING until payment success (Phase 13) → confirm + token
10. Return booking + payment link (Phase 13 stub now)

Cancel:
- window: can cancel if start time > cancel_lead_minutes (setting)
- status → CANCELLED; slot count decremented accordingly (active-count only)
- queue entry (if any) → CANCELLED
- cancellation reasons required for admin cancels

## 9. Flutter Changes

- None (Phase 15). API contract fixed: `/bookings` create/GET, `/my/token`.

## 10. Staff/Admin Changes

- None (Phase 16). Admin cancel API ready.

## 11. Permissions

- `bookings.create` (farmer), `bookings.view.own`
- `bookings.view_any` (staff scoped), `bookings.cancel_any`
- `tokens.view_own`, `tokens.view_any`
- Crop catalog: `crops.view` (public bookable)

## 12. Validation

- BookingValidator: centre/slot/crops required; crop ids exist; `crops` array 1..N; qty min 1; booking date ≥ today; slot time within centre hours; cancel reasons on admin cancel

## 13. Error Handling

- SLOT_FULL, DUPLICATE_BOOKING (one-active), CENTRE_INACTIVE, INVALID_SLOT, BOOKING_WINDOW_CLOSED, BOOKING_NOT_FOUND, CROP_NOT_FOUND, CANCELLATION_WINDOW_CLOSED, TOKEN_EXISTS, CONCURRENT_UPDATE (capacity race), FARMER_NOT_APPROVED

## 14. Security

- Scope: farmers can only create own; staff scoped to their centre/district
- Token generation uses secure random; not sequential-guessable; store hash + display copy
- Booking id references resolved within user scope always
- Prevent booking on CANCELLED/REVERSED booking reuse

## 15. Logging/Audit

- audit: booking create, cancel (with reason), admin cancel, token issue, confirm
- application.log: cron expire runs

## 16. Notifications

- Booking confirmed → PUSH + IN_APP (farmer)
- Cancellation → PUSH + IN_APP (farmer)
- (wired Phase 14; events enqueued now via notification_logs)

## 17. Configuration Changes

- `booking.lead_hours_ahead_min=24` (or per decision), `booking.horizon_days=7`, `booking.min_lead_hours=2`, `booking.cancel_lead_minutes=120`, `booking.max_active=1`, `auto_confirm_on_payment=false` default

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] Create booking → PENDING + booking_crops rows
- [ ] Rules enforced (active limit, window, horizon, center active, capacity)
- [ ] Capacity race handled (no oversell under parallel test)
- [ ] Confirm → token issued (unique), stored hashed
- [ ] Cancel within window works; after window → blocked (unless admin)
- [ ] Cancel clears active counts + (existing) queue entry
- [ ] Booking list + filters + pagination
- [ ] Admin cancel with reason (audited)

## 20. Testing Checklist

- [ ] Multi-crop booking creates N booking_crops, ONE token
- [ ] Second active booking → 409 DUPLICATE_BOOKING
- [ ] Full slot → 409 SLOT_FULL; parallel 2 requests last slot → 1 success
- [ ] Past-date slot → 400 BOOKING_WINDOW_CLOSED
- [ ] INACTIVE centre → 400 CENTRE_INACTIVE
- [ ] Cancel within window → CANCELLED, no payment link
- [ ] Cancel after window → 409 CANCELLATION_WINDOW_CLOSED
- [ ] Admin cancel with reason → audited
- [ ] Token unique across bookings; stored hashed in DB

## 21. What NOT to Implement

- No queue call flow (Phase 11)
- No procurement (Phase 12)
- No payments (Phase 13)
- No reschedule feature (documented not-implemented)
- No refund engine yet (decision/Phase 13 note)

---

**Depends on**: Phase 05, 08, 09
**Feeds into**: Phase 11+