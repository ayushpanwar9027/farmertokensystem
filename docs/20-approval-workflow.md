# Approval & Rejection Workflow

## Overview

Approval/rejection is used where required: farmer verification, staff approval, corrections, sensitive procurement changes, payment corrections. **No arbitrary status toggling** — every decision follows a structured review path and is audited.

## Core Pattern

```
Existing Decision
      ↓
Re-review (initiator)
      ↓
Authorized Review (decision maker)
      ↓
New Decision (APPROVED / REJECTED + reason)
      ↓
Audit Log
      ↓
(Optional) Notification to affected party
```

## Where Approval/Rejection is Used

| Context | Behavior |
|---------|----------|
| Farmer verification | PENDING → APPROVED / REJECTED (reason) |
| Re-review of rejected farmer | REJECTED → PENDING (re-submit) → APPROVED/REJECTED |
| Staff account approval | PENDING → ACTIVE / REJECTED |
| Procurement corrections | Request → REVIEW → APPROVED/REJECTED |
| Payment corrections | Request → REVIEW → APPROVED/REJECTED |
| Sensitive procurement changes | Request → REVIEW → APPROVED/REJECTED |
| Queue order corrections | Request → REVIEW → APPROVED/REJECTED |

## Farmer Verification Workflow

### Flow
```
Farmer registers (mobile OTP verified, details submitted)
      ↓
Farmer status = PENDING (verification)
      ↓
Authorized staff (Manager+/District/Super) reviews farmer details
      ↓
├─ APPROVE → verification_status = APPROVED
│            Farmer can now book slots
│            Notification: "Verification approved"
│            Audit
│
└─ REJECT  → verification_status = REJECTED
             reason required
             Farmer can re-review
             Notification: "Rejected: {reason}"
             Audit
```

### Re-Review
```
REJECTED farmer (with reason)
      ↓
Farmer edits details (or requests re-review)
      ↓
verification_status → PENDING (again)
      ↓
Authorized staff reviews again → APPROVED / REJECTED
```

### Rules
- Reason **required** for rejection
- Decision audited (actor, old/new status, reason)
- Not an arbitrary toggle: each decision is a distinct audited action
- Permission `manage_farmers` + scope

## Approval / Rejection Decision API Design

### Generic Correction Review

```
POST /corrections/{id}/approve  {notes}
POST /corrections/{id}/reject   {reason}
```

Both require:
- Permission `approve_corrections` (Manager+)
- Scope match
- Reason/notes
- Audit entry

Correction record carries: initiator, target entity, field, old value, new value, status.

### Correction Workflow
```
Staff requests correction (e.g., re-inspect weight)
      ↓
Correction status = PENDING (awaiting approval)
      ↓
Reviewer (Manager+/approve_corrections) reviews details
      ↓
├─ APPROVE → apply correction (transactional + audit)
│            Notify initiator
│
└─ REJECT  → keep original, reason recorded
             Notify initiator
```

- No unlimited undo
- Sensitive/production changes follow this path
- Rejected corrections leave original data intact

## Staff Approval Workflow

Staff created by admin become ACTIVE immediately OR PENDING depending on policy. If PENDING:
```
Admin creates staff → PENDING
      ↓
Authorized reviewer reviews → ACTIVE / REJECTED
```

## Priority & Notification

Every decision:
- Records user, timestamp, old/new, reason
- Sends notification (SMS/in-app) to affected farmer
- Creates audit log entry

## Restriction Rules

- Lower-level users cannot approve/reject higher-level items
- No user can approve their own correction (segregation of duties where sensible)
- Decisions logged with IP/USR context
- Reason mandatory on rejection

## Audit Log Fields (for decisions)

```json
{
  "user_id": 50,
  "user_name": "Manager One",
  "action": "APPROVE",
  "module": "PROCUREMENT_CORRECTION",
  "entity_type": "procurement",
  "entity_id": 4001,
  "old_value": { "status": "COMPLETED", "quantity_kg": 480 },
  "new_value": { "status": "COMPLETED", "quantity_kg": 475 },
  "reason": "Scale recalibration on re-inspection",
  "ip": "192.168.1.5",
  "request_id": "req_abc"
}
```

---

**Next**: [21-undo-reversal.md](21-undo-reversal.md) for undo/reversal policies.