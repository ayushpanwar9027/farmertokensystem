# Payment Workflow

## Overview

Payment status tracking for farmer procurements. **Each procurement record has its own payment record.** Only authorized roles can update sensitive payment states.

## Payment Relationship

```
Procurement (1) ──────────── 1:1 ────────────▶ Payment (1)
```

Each procurement (which corresponds to one crop in one booking) links to exactly one payment record.

## Payment Status Lifecycle

```
┌───────────┐  create   ┌────────────┐  authorize  ┌──────────────┐
│  PENDING  │──────────▶│ PROCESSING │────────────▶│     PAID     │
└───────────┘           └─────┬──────┘             └──────────────┘
                               │
                               │ fail (reason)
                               ▼
                        ┌──────────────┐
                        │    FAILED    │
                        └──────┬───────┘
                               │ retry
                               ▼
                        ┌──────────────┐
                        │  PROCESSING  │
                        └──────────────┘
```

### Valid Transitions
| From | To | Requirement |
|------|----|-------------|
| PENDING | PROCESSING | manage_payments (Manager+) |
| PROCESSING | PAID | manage_payments + reference |
| PROCESSING | FAILED | manage_payments + reason |
| FAILED | PROCESSING | manage_payments (retry) |
| PAID | (no change) | PAID is final; reversal is District+ & audited |

## Payment Fields

- `procurement_id` (unique, 1:1)
- `centre_id`
- `user_id` (farmer receiving)
- `amount` (computed from procurement)
- `status`
- `payment_method` (NEFT, RTGS, cheque, cash, UPI)
- `reference` (UTR/cheque no/txn ID)
- `processed_at`, `processed_by`
- `failure_reason`
- `notes`

## Payment Flow (Staff)

### 1. Payment Created (auto)
When a procurement is created (in-progress), a payment placeholder is auto-created:
- amount = quantity × rate
- status = PENDING

### 2. Procurement Completed
When procurement → COMPLETED:
- Verify amount (finalize with verified quantity × rate)
- Payment ready for processing (still PENDING)

### 3. Initiate Payment (Manager+)
`PATCH /payments/{id}/status {status:"PROCESSING", payment_method, notes}`
- PENDING → PROCESSING
- Sets method
- Notification: "Payment processing"

### 4. Mark Paid (Manager+)
`PATCH /payments/{id}/status {status:"PAID", reference}`
- PROCESSING → PAID
- Requires reference (UTR/cheque no/txn ID)
- Sets processed_at/by
- Notification: "Payment of ₹X paid. Ref: UTR147258"

### 5. Mark Failed (Manager+)
`PATCH /payments/{id}/status {status:"FAILED", failure_reason}`
- PROCESSING → FAILED
- Reason required
- Notification: "Payment failed. Contact support."

### 6. Retry
`PATCH /payments/{id}/status {status:"PROCESSING"}` (from FAILED)
- Re-initiate

## Authorization Matrix for Payment Updates

| Role | Can update payments? |
|------|----------------------|
| Super Admin | ✓ (any scope) |
| District Admin | ✓ (own district) |
| Centre Manager | ✓ (own centre) |
| Centre Operator | ✗ (view only) |
| Farmer | ✗ (view own only) |

**Enforced server-side** (permission `manage_payments` + scope).

## Payment Reversal (District+)

Isolated, audited reversal path (not a simple status toggle):
- Request reversal with reason
- Requires `manage_payments` + District+ scope
- Sets status → process + audit entry (old value PAID → new value, reason)
- Cannot be done casually by operator

## Farmer View (Read-only)

Farmer sees payment status via `GET /payments` (own only) or within procurement/booking detail.

Display:
- Crop, quantity, rate, amount
- Status badge (PENDING/PROCESSING/PAID/FAILED)
- Reference (if PAID)
- Payment method

## Duplicate Payment Prevention

- Unique `procurement_id` on payments → one payment per procurement
- Guarded update on status transition (only 1 row affected)
- Re-check before PAID; cannot double-pay

## Reports & Aggregation

Payment reports (scope):
- Daily/monthly totals
- By centre, by crop, by status
- Pending/processing/paid/failed breakdowns
- Charts for admin dashboard (Chart.js)

## Notifications

| Event | Farmer SMS |
|-------|-----------|
| Payment PROCESSING | "Your payment is being processed." |
| Payment PAID | "₹X paid to your account. Ref: UTR..." |
| Payment FAILED | "Payment failed. Please contact support." |

## Audit Events

- Payment created (auto, quiet)
- Payment PROCESSING
- Payment PAID (reference, amount, actor)
- Payment FAILED (reason, actor)
- Payment reversal (reason, actor, old → new)

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | /payments | List (scope) |
| GET | /payments/{id} | Detail |
| PATCH | /payments/{id}/status | Status update (authorized) |
| POST | /payments/{id}/correction | Correction request (audited) |

---

**Next**: [20-approval-workflow.md](20-approval-workflow.md) for approval/rejection workflows.