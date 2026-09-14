# Phase 12 — Procurement + QC + Approval Workflow

## 1. Objective

Implement procurement processing: from call/start through weight/QC capture per crop line, approval (or auto-approve per config), rejection with reasons, and hand-off to payment. Each crop of a booking gets its OWN procurement record.

## 2. Prerequisites

- Phase 10 (bookings/booking_crops), Phase 11 (queue state), Phase 05 (scope)

## 3. Features

- Procurement creation at `start` (from called token) — one per crop line of the booking
- Fields: procurement_number (unique), crop, booked_qty, accepted_weight, damaged_qty, grade, moisture, quality_notes, photos (file refs), statuses
- Status flow: PENDING → VERIFIED | REJECTED | (in-progress processing) → (approval) VERIFIED/REJECTED/CANCELLED
- Rejection requires reason (select + note)
- Approval policy configurable: auto-approve on operator verify OR manager-district approval required (per centre/district setting)
- Rate/malformed data adjustments? (beyond scope — record notes only)
- Payment amount computation done in Phase 13 (uses accepted_weight × rate)
- **Do NOT implement**: price/rate table yet? — decision: crop catalog has default `rate_per_kg` editable by district manager for centre/date (Phase 13 details). Here capture weight + grade only.

## 4. Files to Create

```
app/Controllers/Operator/ProcurementController.php (start/verify/reject)
app/Controllers/Admin/ApprovalController.php (approve/reject pending approvals)
app/Services/ProcurementService.php
app/Services/ApprovalService.php
app/Models/Procurement.php
app/Models/ProcurementAudit.php (optional)
app/Validators/ProcurementValidator.php
```

## 5. Files to Modify

- `config/routes.php` (`/operator/procurements`, `/admin/approvals`)
- `app/Middleware/ScopeMiddleware.php` (procurement scope center+district)

## 6. Database Changes

- Uses procurements (Phase 02). Verify columns include: booking_id, booking_crop_id, centre_id, procurement_number, crop_id, status, booked_qty, accepted_weight, damaged_qty, grade, moisture_pct, quality_notes, rejected_reason, approved_by, approved_at, approved_amount (later), operator_note
- Add migration if missing: `approved_amount DECIMAL(12,2) NULL`

## 7. API Changes

Operator (centre-scoped):
- `POST /operator/queue/{entryId}/start` → creates procurements for all crops, marks entry IN_PROGRESS, returns procurement list
- `PUT /operator/procurements/{id}` (capture weight/grade/moisture/notes/photos)
- `POST /operator/procurements/{id}/submit` (PENDING → VERIFY pending approval)
- `POST /operator/procurements/{id}/reject` (reason required)
- `GET /operator/procurements?status=&date=&q=` 

Admin (manager/district):
- `GET /admin/approvals?centre_id=&status=` (pending list)
- `POST /admin/approvals/{id}/approve` (VERIFIED + sets approved_by/at)
- `POST /admin/approvals/{id}/reject` (reason required)
- `GET /admin/procurements/{id}` detail

Farmer:
- `GET /my/procurements` (own records + status)
- `GET /my/procurements/{id}`

## 8. Backend Logic

ProcurementService:
- start: from CALLED/IN_PROGRESS queue entry → for each booking_crop create PROC pending; mark entry IN_PROGRESS; if booking has crops with zero → skip
- update: capture measured fields (validated ranges); photos upload via FileService
- submit: mark PENDING_APPROVAL if policy `approval_required` else auto VERIFIED (config per district/centre setting `procurement.approval_required`)
- reject: must have reason; status REJECTED
- approve: only from PENDING_APPROVAL; sets approve meta; triggers payment creation (Phase 13 hook)
- Multi-crop: each crop independent status (no all-or-nothing)

Approval flow variants:
- Auto: VERIFIED on submit (operator verifies) → straight to payment computation
- Manual: PENDING_APPROVAL → manager/district approves → VERIFIED → payment

## 9. Flutter Changes

- None (Phase 15): app fetches own procurements list

## 10. Staff/Admin Changes

- None (Phase 16): operator capture screen + approval screen use these APIs

## 11. Permissions

- `procurements.create` (operator), `procurements.update` (operator, own centre), `procurements.approve` (centre manager+ / district admin), `procurements.reject`
- `procurements.view_any` (scoped)
- Farmer own-view

## 12. Validation

- accepted_weight ≥ 0; damaged_qty ≤ booked_qty; moisture range 0-100; grade enum list (A/B/C/REJECT); reason required on reject; photos ≤ max
- Cannot start without CALLED/IN_PROGRESS queue entry
- Cannot capture after VERIFIED/REJECTED (immutable)

## 13. Error Handling

- PROC_NOT_FOUND, ENTRY_NOT_ELIGIBLE, STATUS_IMMUTABLE, REASON_REQUIRED, WEIGHT_INVALID, GRADE_INVALID, APPROVAL_POLICY_MISMATCH, CENTRE_MISMATCH

## 14. Security

- Scope: operators only own centre; approvals scoped district for district admins
- Verified immutable — no silent edits
- Photo refs via file service (authorized download)
- Approval authority logged

## 15. Logging/Audit

- audit: start, submit, reject (reason), approve (who), any edit post-submit blocked + logged

## 16. Notifications

- On VERIFIED → notify farmer procurement summary (Phase 14)
- On REJECTED → notify farmer with reason

## 17. Configuration Changes

- `procurement.approval_required=true` default per centre/district (setting override)
- `procurement.max_photos=3`, `procurement.weight_precision=2`

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] start creates one procurement per crop, entry IN_PROGRESS
- [ ] Capture + submit flow
- [ ] Auto vs manual approval policy honored (per centre setting)
- [ ] Rejection with reason works; record immutable after VERIFIED
- [ ] Approval triggers Phase 13 payment creation hook
- [ ] Farmer own listing correct

## 20. Testing Checklist

- [ ] Start on CALLED token (multi-crop booking) → N procurements, entry IN_PROGRESS
- [ ] Capture valid weights → ok; weight > booked → 400 WEIGHT_INVALID (per business: allowed? set cap rule)
- [ ] Submit with approval required → PENDING_APPROVAL
- [ ] Submit auto policy → VERIFIED + payment record created (after 13 wired; stub store)
- [ ] Reject no reason → 400 REASON_REQUIRED
- [ ] Edit VERIFIED → 409 STATUS_IMMUTABLE
- [ ] Other-centre operator start → 403

## 21. What NOT to Implement

- No payment computation (Phase 13)
- No rate table UI (Phase 13)
- No delivery invoice/print
- No e-way/geo features

---

**Depends on**: Phase 10, 11
**Feeds into**: Phase 13+