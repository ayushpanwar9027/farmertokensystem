# Phase 17 — Integration + Testing + Security Hardening

## 1. Objective

Integrate all pieces (app + portal + backend + cron + notifications), run the full test checklist (Phase 38), harden security (Phase 28), fix defects, and prepare the demo environment for SIH.

## 2. Prerequisites

- Phases 01-16 complete and working in dev/staging

## 3. Features

- Full-stack smoke: farmer app ↔ API ↔ DB ↔ portal ↔ **OneSignal push + OTP gateway (staging keys)**
- Cron job regression (Phase 35) all jobs run & log OK
- Security hardening pass per [28-security.md](28-security.md) + [30-error-handling.md]
- Performance sanity (indexes, pagination, polling load)
- Concurrency tests (slot race, call-next race, release race)
- **2FA login flow + step-up challenge verified end-to-end**
- Seed richer demo data set for SIH showcase (--demo)
- Defect log updates; retest fixes
- Document open decisions resolution (PROJECT-STATE)

## 4. Files to Create

```
scripts/smoke_test.sh (or .php) — automated endpoint checks
scripts/load_test.php (simple parallel booking/queue sim)
scripts/security_scan.php (basic checks: headers, SQLi probes, upload probes — dev only)
docs/TEST-REPORT.md (results summary)
```

## 5. Files to Modify

- Backend: any defect fixes found
- `.env.example` (finalized)
- `PROJECT-STATE.md` (status + decisions)

## 6. Database Changes

- None beyond fixes; possibly additional index based on EXPLAIN findings

## 7. API Changes

- None new (fix only)

## 8. Backend Logic

- Hardening:
  - Ensure all queries parameterized (audit)
  - Upload validation tightened
  - Rate limits verified on login/OTP/booking/sms
  - Maintenance bypass only super_admin
  - Session cookie flags set
  - CORS minimal
  - Header policy (CSP, HSTS once HTTPS)
  - Logs sanitized (no tokens/secrets/legit PII)
- Load sim keeps concurrency ≤ what shared hosting can handle; document ceiling

## 9. Flutter Changes

- Fix defects from Phase 38 checklists; polish empty/error states

## 10. Staff/Admin Changes

- Fix defects; add missing empty/loading states; a11y color contrast

## 11. Permissions

- Re-verify matrix across all endpoints (automated script enumerates protected routes vs permission)

## 12. Validation

- Edge cases: multi-crop zero qty, negative weights, future-dated slots beyond horizon, duplicate concurrent cancels, double call-next, double release, OTP reuse, token reuse, cross-district id juggling

## 13. Error Handling

- Ensure every known error code returned end-to-end without stack traces in prod mode
- 5xx fallback safe

## 14. Security

- Full check against [28-security.md]: 
  - SQLi, XSS (app+portal), CSRF, auth bypass, role escalation, IDOR/scope, file traversal, upload bombs, rate-limit bypass, token leakage, session fixation, secret exposure, brute force lockouts, TLS
- Run dev-only probes; fix all critical/high

## 15. Logging/Audit

- Sample audits correct per action (who/what/when/request_id)
- Alert simulation triggers monitoring event correctly (Phase 33)

## 16. Notifications

- Verify OneSignal push delivery + retry/dedup in staging
- Verify OTP gateway delivery (register, 2FA, reset, mobile_change), retry, dedup, hi-locale templates
- Test admin test-push to single device + all devices

## 17. Configuration Changes

- Production-ready defaults reviewed (approval policy, cancellation window, rate limits)
- Record final decisions table in PROJECT-STATE

## 18. Dependencies

- None new

## 19. Completion Criteria

- [ ] Smoke script all green (dev + staging)
- [ ] Security scan: no critical/high findings
- [ ] Concurrency tests pass (no oversell/double-serve/double-release)
- [ ] Load sim within hosting comfort; documented ceiling
- [ ] All Phase 38 checklist items executed + logged
- [ ] Demo data seeded (5 centres, 3 districts, 15 farmers, slots, bookings, queue, procurements, payments)
- [ ] TEST-REPORT.md written

## 20. Testing Checklist

(as Phase 38 expanded: auth, booking, queue, procurement, payment, admin, app widgets, portal pages, security, performance)

## 21. What NOT to Implement

- No new features
- No refactors outside defect fixes
- No infra addition
- No CI/CD

---

**Depends on**: Phases 01-16
**Feeds into**: Phase 18