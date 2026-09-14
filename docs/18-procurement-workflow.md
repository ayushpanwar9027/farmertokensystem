# Procurement Workflow

## Overview

Tracks procurement of farmer crops at centres. **One booking = one queue token, but each crop in the booking gets its own procurement record.** Each procurement record is linked to its own payment.

## Core Relationship

```
Booking (1)
  │
  ├── Queue Entry (1)  ← one token, one queue position
  │
  └── Booking Crops (N)  ← multiple crops declared at booking
        │
        └── Procurements (N)  ← one procurement record PER crop
              │
              └── Payments (N)  ← one payment per procurement
```

### Example
```
Booking #1001
  - Queue Entry #3001  (token APMCPNQ-20260910-0042)
  - Crop: Wheat (500kg)   → Procurement #4001 → Payment #6001
  - Crop: Soybean (300kg) → Procurement #4002 → Payment #6002
```

## Procurement Status Lifecycle

```
┌───────────┐   verify   ┌───────────┐   start   ┌──────────────┐
│  PENDING  │───────────▶│  VERIFIED │──────────▶│  IN_PROGRESS │
└───────────┘            └───────────┘           └──────┬───────┘
      │                        │                        │ complete
      │ reject (reason)        │ reject (reason)        │
      ▼                        ▼                        ▼
┌───────────┐            ┌───────────┐           ┌───────────┐
│  REJECTED │            │  REJECTED │           │ COMPLETED │
└───────────┘            └───────────┘           └───────────┘
```

### Valid Transitions
| From | To | Action | Notes |
|------|----|--------|-------|
| PENDING | VERIFIED | Verify quality/weight | Operator |
| PENDING | REJECTED | Reject | Reason required |
| VERIFIED | IN_PROGRESS | Start processing | Operator |
| VERIFIED | REJECTED | Reject | Reason required |
| IN_PROGRESS | COMPLETED | Complete | After all processing |
| (any) | correction | Correction request | Manager+, audited |

## Procurement Fields

Per procurement record:
- `booking_id` (links to booking)
- `booking_crop_id` (optional link to declared crop)
- `centre_id`
- `user_id` (farmer)
- `crop_name`
- `variety`
- `quantity_kg` (verified weight)
- `quality_grade` (A/B/C)
- `moisture_percent`
- `status`
- `rate_per_kg` (set before completion)
- `amount` (quantity × rate, computed)
- `notes`
- `rejection_reason`
- Timestamps: verified_at/by, started_at/by, completed_at/by

## Create Procurements (for a Booking)

When operator starts processing a farmer's booking:

`POST /bookings/{bookingId}/procurements`

**Request**
```json
{
  "crops": [
    { "crop_name": "Wheat", "variety": "Lok-1", "quantity_kg": 480,
      "quality_grade": "A", "moisture_percent": 12.5, "rate_per_kg": 22.50 },
    { "crop_name": "Soybean", "variety": "JS-9560", "quantity_kg": 290,
      "quality_grade": "B", "moisture_percent": 11.0, "rate_per_kg": 45.00 }
  ]
}
```

**Server-side (transactional):**
1. Validate booking is CONFIRMED/IN_PROGRESS and belongs to centre scope
2. Validate queue entry exists and booked crops match (loosely)
3. BEGIN TRANSACTION
4. For each crop:
   - INSERT procurements (status=PENDING)
   - INSERT payment placeholder (status=PENDING, amount=quantity×rate)
5. UPDATE queue_entries → IN_PROGRESS (if not already)
6. COMMIT
7. Return created procurements

> The actual crop quantities/quality at the counter may differ slightly from what the farmer declared at booking. The procurement record stores the **verified actual** values. The booking_crop is the declared intent; procurement is the actual processing record. They're linked by booking_crop_id when possible.

## Verification Step

Operator verifies the actual quality/weight:

`PATCH /procurements/{id}/status {status:"VERIFIED"}`

- Confirm quantity matches scale reading
- Grade quality (A/B/C)
- Record moisture (if applicable)
- Requires `manage_procurement` permission

## Complete Procurement

`PATCH /procurements/{id}/status {status:"COMPLETED"}` (from IN_PROGRESS)

- Marks final quantity, grade, amount
- Sets completed_at/by
- Payment record moves PENDING → (ready for PROCESSING)
- Notification sent: procurement completed

### Queue progression
- When ALL procurements for a booking are COMPLETED → booking marks COMPLETED
- Queue entry → COMPLETED

## Reject Procurement

`PATCH /procurements/{id}/reject {reason}`
- Without reason → validation error `REJECTION_REASON_REQUIRED`
- Sets status → REJECTED + reason
- Notification sent: rejection + reason
- Does NOT hard-delete; record retained for audit

## Corrections (Limited)

After COMPLETED, corrections possible but controlled:
- Operator: minimal correction within own centre (requires reason, audited)
- Manager: centre-level correction
- District: higher-level correction
- Super: system-level override
- Sensitive/financial corrections require `approve_corrections`

Correction flow:
```
Correction Request → Review → Approve (approve_corrections) → Apply (audited)
                       └→ Reject (reason)
```

No unlimited undo. No arbitrary status toggling.

## Multiple Procurements per Booking — Business Rules

- One booking → exactly one queue entry/token
- Multiple crops → multiple procurement records
- Each crop's procurement is independent (own status, own payment)
- Booking completes when all its procurements complete (or are rejected/cancelled with note)
- Farmer sees all procurements + payments in app

## Notifications

| Event | Triggered |
|-------|-----------|
| Procurement created | (usually quiet, in-progress) |
| Procurement VERIFIED | quiet |
| Procurement COMPLETED | Farmer SMS: "Procurement complete. Payment processing." |
| Procurement REJECTED | Farmer SMS: reason |
| Payment PROCESSING | Farmer SMS |
| Payment PAID | Farmer SMS: amount + reference |

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| POST | /bookings/{id}/procurements | Create procurement(s) for booking |
| GET | /procurements | List (scope) |
| GET | /procurements/{id} | Detail |
| PATCH | /procurements/{id}/status | Transition (verified/in_progress/completed) |
| PATCH | /procurements/{id}/reject | Reject with reason |
| PATCH | /procurements/{id}/correct | Correction request |

## Audit Events

- Procurement created
- Procurement verified
- Procurement started
- Procurement completed
- Procurement rejected (reason)
- Procurement corrected (old/new, reason)
- Booking completed (all procurements done)

## Data Integrity & Non-deletion

- Completed procurements cannot be casually deleted
- Only Super Admin (or cron) may reverse/soft-delete, with reason + audit
- Rejected/Completed retained for reports & compliance

---

**Next**: [19-payment-workflow.md](19-payment-workflow.md) for payment workflow.