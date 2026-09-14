# Phase 00 — Project Definition & Business Rules

## 1. Objective

Define the project scope, stakeholders, business rules, and success criteria before any implementation. Produce a consistent set of rules that all later phases follow.

## 2. Prerequisites

- None (documentation phase)

## 3. Features

- Confirm problem statement (SIH 26032)
- Confirm stakeholders and roles
- Finalize technology stack
- Define high-level business rules
- Define key status enums and constraints
- Identify configurable parameters (no hardcoding government policies)
- Document the multi-crop booking rule:
  - **One booking → one queue token/entry**
  - **One booking → multiple crops → one procurement record per crop → one payment per procurement**

## 4. Files to Create

- `docs/00-project-overview.md`
- `docs/01-requirements.md`
- `docs/15-business-rules.md`

## 5. Files to Modify

- None

## 6. Database Changes

- None (design only)

## 7. API Changes

- None (design only)

## 8. Backend Logic

- None (design only)

## 9. Flutter Changes

- None (design only)

## 10. Staff/Admin Changes

- None (design only)

## 11. Permissions

- None (design only)

## 12. Validation

- Rule consistency: verify each business rule has an owner/implementation phase
- Verify status enums are complete and non-conflicting

## 13. Error Handling

- None (design only)

## 14. Security

- Confirm security baseline principles (HTTPS, hashing, RBAC, audit) documented

## 15. Logging/Audit

- Confirm principle: all important actions auditable

## 16. Notifications

- Confirm notification event list (booking, cancel, verification, queue, call, procurement, payment)
- Confirm channels: OneSignal push + in-app; OTP via OTP gateway (2FA/registration); keys hardcoded for now

## 17. Configuration Changes

- Finalize the configurable parameters table (see 01-requirements.md)
- Mark unresolved business decisions as "open decisions" (no silent policy invention)

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] Requirement docs approved
- [ ] Business rules table complete and consistent
- [ ] Multi-crop booking rule documented (booking→token→queue; crop→procurement→payment)
- [ ] Open decisions recorded in PROJECT-STATE.md

## 20. Testing Checklist

- N/A (documentation only)

## 21. What NOT to Implement

- No code
- No database
- No infrastructure
- Do NOT invent official government policy (e.g., exact cancellation window) — record as configurable/open

---

**Depends on**: none
**Feeds into**: Phase 01 (architecture), all later phases