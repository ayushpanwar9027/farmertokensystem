# Undo & Reversal Policy

## Overview

The system does **not** provide unlimited undo. Instead, it uses structured, permission-controlled, audited reversal/soft-delete actions. Destructive deletion is avoided in favor of status transitions (CANCELLED, REJECTED, REVERSED, INACTIVE).

## Principles

1. No unlimited undo
2. All reversals require: reason + permission + audit log
3. Use status transitions, not destructive deletes
4. Authority scales with role level

## Reversal Authority Matrix

| Action | Operator | Centre Manager | District Admin | Super Admin |
|--------|:--------:|:--------------:|:--------------:|:-----------:|
| Booking cancellation (own farmer) | - | - | - | - |
| Booking cancellation (staff, with reason) | ✓ (limited) | ✓ | ✓ | ✓ |
| Queue operational correction | ✓ (limited) | ✓ | ✓ | ✓ |
| Centre-level correction | - | ✓ | ✓ | ✓ |
| District-level correction | - | - | ✓ | ✓ |
| Pending procurement correction | ✓ (limited) | ✓ | ✓ | ✓ |
| Completed procurement reversal | - | - (with approval) | ✓ | ✓ |
| Payment reversal | - | - | ✓ | ✓ |
| System-level override | - | - | - | ✓ |

## Reversal Paths

### 1. Booking Cancellation (Farmer)
```
Farmer requests cancellation (within window)
    ↓
Validate window + reason
    ↓
Booking → CANCELLED (reason, actor)
Queue entry → CANCELLED
Token → REVOKED
Slot capacity freed
```

### 2. Queue Operational Correction (Operator)
- E.g., accidentally skipped a farmer
- Operator can re-call / restore within a short window (e.g., 5 min)
- Requires reason + audited
- Manager can at any time

### 3. Procurement Correction (Pending → Verified)
- Before COMPLETED, operator may correct quantity/grade
- Requires reason + audited

### 4. Completed Procurement Reversal (Manager+/District+)
```
Request reversal (reason)
    ↓
Review (approve_corrections)
    ↓
Procurement status → REVERSED (or a REJECTED-like terminal)
Payment record handled accordingly
    ↓
Audit + notify farmer
```

### 5. Payment Reversal (District+)
```
Request reversal (reason)
    ↓
Requires District+ scope + manage_payments
    ↓
Payment → FAILED/REVERSED with reason
    ↓
Audit + notify farmer
```

### 6. System-Level Override (Super Admin)
- Only Super Admin can reverse/override in exceptional cases
- Requires explicit reason
- Audited

## Terminal vs Reversible States

| State | Reversible? | Who |
|-------|-------------|-----|
| CANCELLED (booking) | Yes, create new booking | Farmer (new) |
| REJECTED (verification/procurement) | Yes, re-review | Farmer/Staff |
| REVERSED | No (terminal marker) | - |
| COMPLETED | Only via reversal (District+) | District+ |
| PAID | Only via reversal (District+) | District+ |
| INACTIVE (centre/staff) | Yes, reactivate | Authorized |

## No Destructive Deletes

Important records are never hard-deleted:
- Bookings, tokens, queue entries, procurements, payments
- Centres (once used) → use INACTIVE
- Staff → use INACTIVE
- Slots → soft-delete (if unreferenced) or INACTIVE (if referenced)

Deletion only where safe & unreferenced (e.g., truly orphaned temp files).

## Audit Requirement

Every reversal records:
- Who (user_id, role)
- What (entity_type, entity_id)
- Old value → New value
- Reason (mandatory)
- Timestamp, IP, request_id
- Approval info (if routing through approval)

---

**Next**: [22-notification-system.md](22-notification-system.md) for notification design.