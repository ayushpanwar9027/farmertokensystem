# Booking Workflow

## Overview

Farmers book slots at procurement centres. Each booking creates a digital token and a queue entry. A booking can contain multiple crops (one procurement record per crop).

## Booking Flow

```
Farmer App                      PHP API
     │                              │
     │ 1. Browse Centres            │
     ├─────────────────────────────▶│  GET /centres
     │◀─────────────────────────────┤  list (filtered by district/active)
     │                              │
     │ 2. Select Centre + Date      │
     ├─────────────────────────────▶│  GET /centres/{id}/available-slots?date=
     │◀─────────────────────────────┤  slot list with capacity
     │                              │
     │ 3. Select Slot               │
     │    (UI shows remaining)      │
     │                              │
     │ 4. Add Crops (multi)         │
     │    crop_name, variety,       │
     │    quantity_kg               │
     │                              │
     │ 5. Confirm                   │
     ├─────────────────────────────▶│  POST /bookings
     │  {centre_id, slot_id,        │
     │   date, crops:[...]}         │
     │                              │  BEGIN TRANSACTION
     │                              │   ├─ Validate farmer APPROVED
     │                              │   ├─ Validate no duplicate active booking
     │                              │   ├─ Lock slot (FOR UPDATE)
     │                              │   ├─ Check booked_count < capacity
     │                              │   ├─ INSERT bookings (CONFIRMED)
     │                              │   ├─ INSERT booking_crops (N)
     │                              │   ├─ Generate token (unique)
     │                              │   ├─ INSERT tokens
     │                              │   ├─ Compute queue position
     │                              │   ├─ INSERT queue_entries (WAITING)
     │                              │  COMMIT
     │◀─────────────────────────────┤  {booking, token, queue_entry, crops}
     │                              │
     │    Show booking confirmation │
     │    + QR token + position     │
     │                              │
     │                              │  Enqueue notification (async)
     │                              │   - BOOKING_CONFIRMED SMS
     │                              │   - In-app notification
```

## Pre-Conditions (Validations)

Before booking is accepted, server validates:

1. **Authentication** - Farmer logged in (JWT valid)
2. **Role** - user role = FARMER
3. **Verification** - farmer.verification_status = APPROVED
   - PENDING → error "awaiting verification"
   - REJECTED → error with reason
4. **Centre exists & ACTIVE**
5. **Slot exists & ACTIVE** (not FULL, not INACTIVE)
6. **Slot in future** (date >= today, within schedule)
7. **Capacity** - booked_count < capacity
8. **No duplicate active booking** for this farmer (per policy)
   - No other CONFIRMED booking for same date (configurable: same-day or same slot)
9. **Crops validation**:
   - At least 1 crop
   - Each crop has non-empty name + quantity > 0
   - Total quantity within reasonable limits (configurable max)
10. **Rate limiting** - within booking rate limit

## Transaction (Booking Creation)

```sql
START TRANSACTION;

-- 1. Farmer checks
SELECT id, role_id, verification_status FROM users u
JOIN farmers f ON f.user_id = u.id
WHERE u.id = ? AND u.status = 'ACTIVE'
  AND f.verification_status = 'APPROVED'
FOR UPDATE;

-- 2. Duplicate active check
SELECT COUNT(*) FROM bookings
WHERE user_id = ?
  AND status IN ('PENDING','CONFIRMED')
  AND date = ?;

-- 3. Lock slot + check capacity
SELECT capacity, booked_count, status FROM slots
WHERE id = ? FOR UPDATE;

IF booked_count >= capacity OR status != 'ACTIVE' THEN
    ROLLBACK; -- SLOT_FULL / SLOT_UNAVAILABLE
END IF;

-- 4. Insert booking
INSERT INTO bookings (booking_number, user_id, centre_id, slot_id, date, status, ...)
VALUES (?, ?, ?, ?, ?, 'CONFIRMED', ...);
$bookingId = LAST_INSERT_ID();

-- 5. Insert crops
INSERT INTO booking_crops (booking_id, crop_name, variety, quantity_kg, ...) VALUES (?, ...);

-- 6. Generate token
INSERT INTO tokens (booking_id, token_number, qr_data, status) VALUES (?, ?, ?, 'ACTIVE');

-- 7. Insert queue entry (compute position)
INSERT INTO queue_entries (booking_id, centre_id, date, status, position, ...)
VALUES (?, ?, ?, 'WAITING', ?, ...);

-- 8. Increment slot booked_count
UPDATE slots SET booked_count = booked_count + 1 WHERE id = ?;

COMMIT;
```

Any failure → ROLLBACK, no partial state.

## Booking Number & Token Generation

### Booking Number
```
Format: BK-YYYYMMDD-SEQUENCE
Example: BK-20260910-0042
SEQUENCE = daily counter (padded to 4)
```
Stored with unique index.

### Token Number
```
Format: CENTRE_CODE-YYYYMMDD-SEQUENCE
Example: APMCPNQ-20260910-0042
CENTRE_CODE = centre.code (uppercase, no spaces)
SEQUENCE = a non-sequential random-looking value derived from server-side counter
```
- Unique index on token_number
- QR data: `FPS|bookingId|tokenNumber`
- Non-guessable (server-side random component with uniqueness enforcement)

## Booking Status Lifecycle

```
                ┌───────────────────────────┐
                │          PENDING          │
                │  (rare - created pending) │
                └─────────────┬─────────────┘
                              │ confirm
                              ▼
                ┌───────────────────────────┐
                │         CONFIRMED         │
                └───────┬───────────┬───────┘
                        │           │
              farmer cancel│       slot date passes
              (within window)      without arrival
                        │           │
                        ▼           ▼
        ┌───────────────┴───┐   ┌───┴────────────┐
        │     CANCELLED     │   │    EXPIRED     │
        │  (reason + audit) │   └────────────────┘
        └───────────────────┘
              (staff/manager can also cancel
               with permission + reason)

                        CONFIRMED
                            │ queue → procurement complete
                            ▼
                      ┌───────────────┐
                      │   COMPLETED   │
                      │ (final state) │
                      └───────────────┘
```

## Cancellation Rules

| Who | When | Requirements |
|-----|------|--------------|
| Farmer | Before slot start - window | Reason + within window (default 120 min) |
| Farmer | After window | Denied (BOOKING_NOT_CANCELLABLE) |
| Operator/Manager | Any time | Permission `manage_bookings` + reason + audit |
| System (cron) | After slot date | Auto EXPIRED |

Cancellation effects:
- Booking status → CANCELLED (reason, actor, timestamp)
- Slot booked_count decremented (frees capacity)
- Queue entry → CANCELLED
- Token → REVOKED
- Notification sent to farmer

## Duplicate Prevention

- Unique active-booking check (transactional)
- Composite index on (slot_id, user_id, status) as optimization
- Backend guard on every booking create

## Concurrency Handling

- Slot row locked with `SELECT ... FOR UPDATE` during booking tx
- Ensures two simultaneous bookings for the same slot both see correct booked_count
- Prevents overbooking

## Locking / Freeing Capacity

- On create: `booked_count = booked_count + 1`
- On cancel/expire: `booked_count = booked_count - 1`
- Slot status recomputed: `FULL` when booked_count >= capacity; `ACTIVE` when space freed

## Notifications Triggered

| Event | Channel | Content |
|-------|---------|---------|
| Booking confirmed | SMS + In-app | Token, centre, date, slot, queue position |
| Booking cancelled | SMS + In-app | Confirmation of cancellation |

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | /bookings | Create booking |
| GET | /bookings | List own bookings (history) |
| GET | /bookings/{id} | Booking detail |
| PATCH | /bookings/{id}/cancel | Cancel booking |
| GET | /bookings/{id}/token | Token data |

## Audit Events

| Event | Logged |
|-------|--------|
| Booking created | audit + notification |
| Booking cancelled | audit (reason, actor) |
| Booking expired (cron) | audit |

## Errors

| Code | When |
|------|------|
| ACCOUNT_PENDING | farmer not verified |
| ACCOUNT_REJECTED | farmer rejected |
| SLOT_FULL | capacity reached |
| SLOT_UNAVAILABLE | slot inactive/missing |
| DUPLICATE_BOOKING | duplicate active booking |
| BOOKING_NOT_CANCELLABLE | outside window |
| VALIDATION_ERROR | crops/fields invalid |
| RATE_LIMITED | too many requests |

---

**Next**: [17-queue-workflow.md](17-queue-workflow.md) for queue workflow.