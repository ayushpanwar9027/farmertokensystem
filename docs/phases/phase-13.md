# Phase 13 — Payments

## 1. Objective

Implement payment generation per procurement record and payment lifecycle (pending → released/paid). One procurement → one payment. Supports UPI/proof-of-payment, release-based disbursal, and reversal cleanup.

## 2. Prerequisites

- Phase 12 (procurement VERIFIED w/ accepted_weight), Phase 10 (bookings), Phase 11 (queue)

## 3. Features

- Rate table: crop base rate per kg; editable per centre/date by district manager (dated override)
- Payment record auto-created on procurement VERIFIED
  - amount = accepted_weight × effective_rate
- Payment statuses: PENDING → INITIATED → RELEASED (PAID) | FAILED | CANCELLED (on procurement reversal)
- Operator payment release: capture payment evidence (bank/UPI ref) → mark RELEASED with reference
- Approval: only CENTRE_MANAGER or DISTRICT_ADMIN can mark RELEASED (policy)
- Payment method: UPI, bank transfer, cash (enum)
- Farmer statement: list of payments + status
- Reversal: when procurement reversed (Phase 12 reject/reverse), payment auto-reversed/cancelled with audit
- Duplicate-release guard (idempotency)

## 4. Files to Create

```
app/Controllers/Operator/PaymentController.php
app/Controllers/Admin/PaymentAdminController.php
app/Services/PaymentService.php
app/Services/RateService.php
app/Models/Payment.php
app/Models/CropRate.php
app/Validators/PaymentValidator.php
database/seeders/crop_rate_seeder.php (default rates)
```

## 5. Files to Modify

- `config/routes.php` (`/operator/payments`, `/admin/payments`, `/my/payments`)
- `app/Console/cron.php` (payment reminder optionally)

## 6. Database Changes

- Uses payments (Phase 02). Verify columns: procurement_id UNIQUE, booking_crop_id, amount, method, payment_reference, status, released_by, released_at, attempts, reversal info
- Add `crop_rates` table if not in Phase 02: crop_id, centre_id (nullable), effective_from, rate_per_kg, is_active

## 7. API Changes

- `POST /admin/crop-rates` + `PUT /admin/crop-rates/{id}` (set rate effective from date; scope district/centre)
- `GET /crops/{crop}/rates?centre_id=&date=` (for amount check)
- `GET /operator/payments?status=&date=&q=` + `PUT /operator/payments/{id}/release` (mark RELEASED with reference; auto-initiate?)
- `GET /admin/payments` (scoped summary + filters)
- `GET /my/payments` (farmer statement)
- `GET /my/payments/{id}`
- reversal hook: `POST /admin/payments/{id}/cancel` (only if PENDING/INITIATED; RELEASED → needs reversal approval flow note)

## 8. Backend Logic

PaymentService:
- on procurement VERIFIED: compute rate via RateService (centre+date specific, else district default, else crop base) → insert payment PENDING amount (precision 2)
- release: must be PENDING/INITIATED; set method + ref; status RELEASED; released_by/at; idempotency: if already RELEASED return current; guard duplicate ref
- initiate_step optional two-phase (INITIATED then RELEASED) to model bank processing
- reversal (procurement REJECTED-after-verified / booking REVERSED):
  - PENDING → CANCELLED
  - INITIATED → CANCELLED (transactional)
  - RELEASED → mark REVERSED with reversal meta (requires super_admin/district_admin approval to confirm money-go-back)
- farmer statement includes status timeline

## 9. Flutter Changes

- None (Phase 15): `/my/payments` consumption

## 10. Staff/Admin Changes

- None (Phase 16): operator payment screen + admin rate/summary screens

## 11. Permissions

- `payments.view` (operator scoped, manager, district), `payments.release` (manager+), `payments.release_operator` (if policy allows operator), `payments.cancel`, `rates.manage` (district_admin+), farmer own view

## 12. Validation

- rate ≥ 0; effective_from date; centre_id must match scope
- release requires method + non-empty reference + status eligible
- cancel only PENDING/INITIATED
- amount precision 2 decimals; kg precision matched to procurement

## 13. Error Handling

- PAYMENT_NOT_FOUND, PAYMENT_STATUS_INVALID, RATE_NOT_FOUND, DUPLICATE_RELEASE, REFERENCE_REQUIRED, SCOPE_MISMATCH, PROC_ALREADY_PAID

## 14. Security

- Amount is computed server-side; never accepts client amount
- Release person scoped; audit trail full
- Reference not exposed beyond needs
- Reversal double-guard (cannot cancel RELEASED through normal API)

## 15. Logging/Audit

- audit: rate create/update; payment release (who, amount, ref masked), cancel, reversal
- notification on release

## 16. Notifications

- Payment RELEASED → PUSH + IN_APP farmer (amount, ref)
- Payment reversed → PUSH + IN_APP farmer

## 17. Configuration Changes

- `payment.allow_operator_release=false` default (only manager+ by default)
- `payment.idempotency_ttl` (skip server caching; DB unique guard on booking_crop_id)

## 18. Dependencies

- None (DB-level unique constraint on payments.procurement_id)

## 19. Completion Criteria

- [ ] Payment auto-created per procurement on VERIFIED
- [ ] Rate resolution per centre+date works (override logic)
- [ ] Release flow with idempotency + dual-guard on RELEASED
- [ ] Cancellation for PENDING/INITIATED
- [ ] Reversal for RELEASED via approval path
- [ ] Farmer statement correct
- [ ] Duplicate release prevented

## 20. Testing Checklist

- [ ] Verify procurement → payment created amount = wt×rate
- [ ] Centre+date override rate differs → amount uses override
- [ ] Release twice → second returns existing, no dup
- [ ] Release without ref → 400 REFERENCE_REQUIRED
- [ ] Release by operator when policy off → 403
- [ ] Cancel RELEASED → 409
- [ ] Procurement reversed RELEASED → payment REVERSED after approval
- [ ] Farmer sees statement sorted + statuses

## 21. What NOT to Implement

- No real gateway/bank API integration (manual/UPI proof flow; documented)
- No refund to farmer accounts linkage (manual note)
- No e-payment provider integration now

---

**Depends on**: Phase 12
**Feeds into**: Phase 14+