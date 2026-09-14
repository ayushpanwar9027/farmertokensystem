# Phase 18 — Deployment + Production Readiness

## 1. Objective

Launch to production shared hosting: final deploy, cron, SSL, backups, monitoring, staff onboarding, and post-launch operational docs.

## 2. Prerequisites

- Phase 17 green (staging accepted)
- Domain + shared hosting + OneSignal + OTP gateway keys

## 3. Features

- Production environment config (APP_ENV=production, DEBUG off)
- Deploy code + .env + permissions per [39-deployment.md]
- Migrations + non-demo seed (roles/permissions/languages/settings/districts + crops/rates base)
- Cron entries (all 12 jobs per [35-cron-jobs.md])
- SSL + forced HTTPS + headers
- Backup jobs verified + test restore (Phase 34)
- Monitoring live: health task, error alerts, cron_runs check, storage watch
- Staff account onboarding (admins set passwords; roles assigned)
- Farmer onboarding guide (push/OTP + portal)
- Final smoke on production domain
- Maintenance-mode-ready runbook

## 4. Files to Create

```
docs/OPS-RUNBOOK.md (day-1 ops, runbooks, escalation, restore, maintenance)
docs/ONBOARDING.md (admin/staff/farmer instructions)
deploy/checklist.md (pre + post deploy)
```

## 5. Files to Modify

- `.env` production (never committed)
- `.htaccess` production headers finalized
- `config.php` production paths
- PROJECT-STATE.md (final status)

## 6. Database Changes

- None structural; initial seed (roles/permissions/settings/languages/districts/crops/rates) only

## 7. API Changes

- None

## 8. Backend Logic

- Verify maintenance bypass; error logging paths writable
- Confirm no debug endpoints active in prod

## 9. Flutter Changes

- Release APK build with prod base URL; install docs

## 10. Staff/Admin Changes

- Deploy portal statics; test all role views on prod

## 11. Permissions

- Verify super_admin can access everything; district/centre scopes work on live data

## 12. Validation

- Prod smoke: health, login, one booking, one queue call, procurement, payment (on test data then clean)

## 13. Error Handling

- Confirm production error responses never leak; logs only

## 14. Security

- HTTPS forced; HSTS (once verified); secrets checked; .env outside webroot; backups secure; quick view of security.log post-launch

## 15. Logging/Audit

- Confirm logs rotating; audit populated; log viewer (admin) works

## 16. Notifications

- OneSignal live keys (APP_ID + REST API KEY in config/onesignal.php); send push + in-app to real devices
- OTP gateway live keys (API_KEY + SENDER_ID + TEMPLATE_ID in config/otp.php); send OTP SMS to real numbers (register, 2FA, reset, mobile_change)
- Admin test-push + test-OTP buttons work

## 17. Configuration Changes

- Final values for all settings; verify test vs production settings differ appropriately

## 18. Dependencies

- Hosting (LAMP shared), OneSignal, OTP gateway, domain SSL
- No CI/CD

## 19. Completion Criteria

- [ ] Production reachable via HTTPS
- [ ] Migrations applied; seeds correct (no demo data)
- [ ] Cron all jobs OK in cron_runs
- [ ] Backup → verified restore once
- [ ] Monitoring alerts functional (simulate)
- [ ] Staff/farmers onboarded
- [ ] Release APK distributed (link)
- [ ] Runbook + onboarding docs written
- [ ] PROJECT-STATE finalized

## 20. Testing Checklist

- [ ] Fresh browser login → portal
- [ ] **2FA login to real phone (OTP)**
- [ ] Booking + cancel window
- [ ] Queue full cycle on test centre
- [ ] Payment release → **push notification**
- [ ] **OneSignal test-push to staff device**
- [ ] **OTP test: register, 2FA enable, reset, mobile_change to real phone**
- [ ] Backups exist; restore dry-run
- [ ] Maintenance on/off via super admin; public sees banner
- [ ] HTTPS redirect from http
- [ ] 404/500 handled gracefully

## 21. What NOT to Implement

- No CI/CD
- No Docker/K8s
- No new features post-launch in this phase
- No multi-district try so far beyond seeded data

---

**Depends on**: Phase 17
**Feeds into**: operations/maintenance (docs)