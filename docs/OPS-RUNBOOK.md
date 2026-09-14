# Operations Runbook (OPS-RUNBOOK)

Production day-1 and incident operations for the Farmer Procurement System.
Companion docs: `docs/39-deployment.md`, `docs/35-cron-jobs.md`,
`docs/34-backup-recovery.md`, `docs/33-monitoring-alerts.md`, `docs/31-logging-audit.md`,
`docs/27-maintenance-mode.md`, `deploy/checklist.md`, `deploy/sync.ps1`.

> Values like `<app_root>`, `<domain>`, `<user>` are placeholder — replace with the
> real host path (`/home/<user>/fps`) and domain before using.

---

## 0. Contact / Escalation

| Tier | Who | How | Expected SLA |
|------|-----|-----|--------------|
| T1 Ops | Deployment owner / hosting support | email + hosting ticket | 4h |
| T2 App admin | Super Admin (named owner) | phone (recorded) | 2h |
| T3 Escalation | Vendor (OneSignal / OTP gateway / hosting) | vendor portals + tickets | per contract |
| Security incident | Super Admin + hosting security | follow §9 | immediate |

Record current names/phones/emails in the "EScalation" section of
`deploy/checklist.md` at launch.

---

## 1. Day-1 Checklist (first shift after launch)

1. `curl -s https://<domain>/health` → `"status":"UP"`, database OK, storage OK.
2. `curl -I https://<domain>` → 301 from http; security headers present (§11).
3. `php app/console/migrate.php status` → all batches applied.
4. Seed row counts per `deploy/checklist.md` §4 (match expected).
5. `SELECT job, status, COUNT(*) FROM (audit run log) GROUP BY ...` — all cron
   jobs ran OK today (check `storage/logs/cron.log` + `audit_logs` reason `cron_*`).
6. Login as Super Admin → dashboard loads; no error banner.
7. `storage/logs/{application,api,security,notification}.log` exist and are writable.
8. Backup cron/manual job ran → milestone file exists in `<app_root>/../backups`.

## 2. Start / Stop / Deploy Repeat

Shared hosting — "start/stop" = maintenance mode (not process control):

- **Open for business**: Super Admin portal → *Maintenance* → OFF.
- **Stop public traffic**: Super Admin portal → *Maintenance* → ON (banner shows).
- **Repeat deploy (incremental)**: follow `docs/39-deployment.md` §"Update
  Deployment Steps" + `deploy/sync.ps1`; always backup first; if breaking, toggle
  maintenance ON first.

## 3. Environment Variables (production only)

`.env` is at `<app_root>/.env`, `chmod 600`, OUTSIDE webroot. Any change
requires a re-read (env is read per-request by `app/bootstrap.php` — no
cache flush needed; PHP opcache may need `opcache_reset` on some hosts after
editing PHP config files, not env).

Key vars (see `deploy/production.env.example`):
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `DB_*`,
`ENCRYPTION_KEY`, `JWT_SECRET`, `ONESIGNAL_APP_ID/REST_API_KEY`,
`OTP_API_KEY/SENDER_ID/TEMPLATE_ID`, `CORS_ALLOWED_ORIGINS`.

> **NEVER** set `APP_DEBUG=true` in production; **NEVER** commit `.env`; store a
> copy of secrets in the password manager (see `docs/26-secret-management.md`).

## 4. Maintenance-Mode Runbook

1. Super Admin → *System → Maintenance* → toggle ON (optional `expected_available_at`).
2. Confirm public sees banner: `curl -s https://<domain>/health/maintenance` → `enabled:true`; anonymous API → 503 `MAINTENANCE_MODE`.
3. Pause time-sensitive cron while down (edit cPanel cron entries or leave —
   jobs are idempotent and safe during maintenance).
4. Test on `/health` (always allowed during maintenance).
5. Toggle OFF and re-verify `/health/maintenance` `enabled:false` + login works.

## 5. Cron Inventory (6 real jobs)

| Job | Freq | Verification |
|-----|------|--------------|
| generate-slots | daily 00:15 | `audit_logs action=SLOTS_GENERATED reason=cron_generate_slots` |
| expire-pending-bookings | every 5 min | `BOOKINGS_EXPIRED ... cron_expire_pending_bookings` |
| expire-unarrived-bookings | every 30 min | `BOOKINGS_EXPIRED ... cron_expire_unarrived_bookings` |
| queue-notify | every 5 min | `QUEUE_NOTIFY ... cron_queue_notify` |
| send-pending-notifications | every 1 min | `SEND_PENDING_NOTIFICATIONS ...` |
| retry-notifications | every 5 min | `RETRY_NOTIFICATIONS ...` |

cPanel entries: `deploy/checklist.md` §5. Log: `<app_root>/storage/logs/cron.log`.

## 6. Backup & Retention

Run on the HOST nightly (set as cPanel cron while `backup-database`/`backup-files`
jobs do not exist in code — see `docs/35-cron-jobs.md` discrepancy note):

```bash
# DB 02:00 (consistent dump + verify)
mysqldump --single-transaction --routines -u fps_user -p'****' farmer_procurement \
  | gzip > /home/user/backups/db/db_$(date +%Y%m%d_%H%M%S).sql.gz
gzip -t /home/user/backups/db/db_$(date +%Y%m%d).sql.gz

# Files 02:30 (app code + config + storage/uploads + .env, .env stays 600)
tar -czf /home/user/backups/files/files_$(date +%Y%m%d_%H%M%S).tar.gz \
  -C /home/user fps/app fps/config fps/database fps/resources fps/storage fps/.env

# Verify latest file archive
tar -tzf /home/user/backups/files/files_$(date +%Y%m%d).tar.gz | head
```

Retention: **daily DB 30 days, monthly (1st) 12 months; daily files 30 days,
monthly 12 months; .env snapshots 12 months.** Off-site copy weekly to secure
storage/device [HUMAN]. Backups live at `/home/user/backups/` — NEVER inside
`public_html/`.

## 7. Restore Procedure (tested monthly; do one live dry-run at launch)

### DB
```bash
gzip -t /home/user/backups/db/db_YYYYMMDD.sql.gz
mysql -u fps_user -p'****' -e "CREATE DATABASE IF NOT EXISTS farmer_procurement_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
gunzip -c db_YYYYMMDD.sql.gz | mysql -u fps_user -p'****' farmer_procurement_restore
mysql -u fps_user -p'****' -e "SELECT COUNT(*) FROM users; SELECT MAX(created_at) FROM audit_logs;" farmer_procurement_restore
```
### Files
```bash
tar -xzf backups/files/files_YYYYMMDD.tar.gz -C /tmp/fps_restore
chmod -R 775 /tmp/fps_restore/storage
# verify permissions + .env intact (600)
```
### Post-restore verification
1. `/health` → UP
2. Login (admin + farmer) works
3. One booking visible
4. OTP test SMS + OneSignal test push OK
5. Maintenance OFF
6. Cron runs normally; record result in `docs/PROJECT-STATE.md`.

**Restore to a scratch location only** for verification; do not overwrite live
files/DB until confident and a new backup is in place.

## 8. Monitoring & Alert Paths

Public: `https://<domain>/health` (DB + storage checks), `/health/maintenance`.

Alert mechanisms in place at launch:
- App errors → `storage/logs/application.log` (+ `error_logs` table schema exists,
  write path not wired — FLAGGED, see `docs/33-monitoring-alerts.md` note).
- Cron results → `audit_logs` with `reason=cron_*` (missing row / FAILED message
  in `cron.log` = alert trigger).
- Notification failures → `notification_logs` + `notification.log`; retry job #6.
- Portal dashboards / reports show operational metrics (bookings, queue,
  storage, errors via existing report endpoints).

**Phase 18 FLAG**: the *cron-driven monitoring checks* + `monitoring_events` +
`cron_runs` tables + admin *monitoring* page described in `docs/33-monitoring-alerts.md`
are **not implemented** in code. Alert simulation at launch therefore covers:
(1) cron failure detection via audit/log review, (2) 500-error → safe envelope +
application.log, (3) storage usage via host quota panel. Fully-automated alert
inbox/email escalation is a **post-launch follow-up** (new features are out of
scope for Phase 18).

### Simulated drill (to run at launch)
```bash
# 1. Forced cron failure (then revert)
php app/console/cron.php --job=expire-pending-bookings --badarg
tail -n 5 storage/logs/cron.log          # expect error line, no audit row

# 2. App error with DEBUG off
#   Trigger a 500 inside a request (e.g. temporarily break a query), verify:
curl -s https://<domain>/api/v1/...      # expect {"success":false,"error":{"code":"SERVER_ERROR",...}}
tail -n 3 storage/logs/application.log    # expect full details incl. trace (log only)
#   revert the change; confirm 200 again

# 3. Storage near limit
#   Host panel: check quota; (optionally) compare settings alert_storage_percent.
```

## 9. Incident Runbooks

### Site down (500 / unreachable)
1. Verify maintenance flag OFF (`/health/maintenance`).
2. Check disk/quota (`df -h` / host panel) and PHP error log (`storage/logs/application.log`).
3. Recent deploy? → rollback (`deploy/checklist.md` §12).
4. DB up? `mysqladmin -u fps_user -p ping`.
5. Escalate T1/T2; keep maintenance ON during fix.

### DB down
1. `mysqladmin ping`; check host panel DB status.
2. Backups verified (latest `gzip -t`).
3. Toggle maintenance ON so users see banner (health stays UP-green for DB? — no,
   /health flags DB down; users still get 503 via maintenance).
4. Contact hosting; restore if needed per §7.
5. Resume + verify cron ran OK.

### Cron stuck / not running
1. `cat storage/logs/cron.log` — empty = not scheduled; verify cPanel entries (`deploy/checklist.md` §5).
2. `php app/console/cron.php --job=<job>` manually once; observe output.
3. Check write perms on `storage/logs`.
4. If still failing → run failed job path drill (§8) → escalate.

### OTP / push outage
1. Outbound SMS: verify `OTP_API_KEY`/`SENDER_ID`/`TEMPLATE_ID` (`.env`), gateway status page.
2. OneSignal: verify `ONESIGNAL_APP_ID`/`REST_API_KEY`, app quota, device `player_id` present (`user_devices`).
3. Check `notification.log` + `notification_logs` statuses; `retry-notifications` will retry within backoff.
4. Provide manual fallback (verbal OTP handoff procedure) to staff; escalate to vendor.

### Storage full
1. Host panel quota; `df -h`.
2. Prune `storage/logs/*` older than retention, `storage/temp/*`, old backups.
3. If <10% remaining → raise alert, review `alert_storage_percent` and IQ.

### Rate-limit burst (429s blocked)
1. Check `rate_limit_logs` counts + `api.log` 429s.
2. Sources: bots? polling too fast? Raise `RATE_LIMIT_API` only after confirming legit traffic.
3. If attack → block offending IP at hosting/WAF; review `security.log`.

### Security incident
1. Record time, preserve evidence: `security.log`, `application.log`, `api.log`, `audit_logs`.
2. Revoke affected sessions (`auth/sessions` admin API) + rotate `JWT_SECRET`/`ENCRYPTION_KEY` per `docs/26`.
3. If DB/backup compromise → restore from last clean backup (§7).
4. Contact hosting provider (IP containment) + security team; create incident number.
5. Post-mortem documented in `docs/PROJECT-STATE.md`.

## 10. Logs

| File | Content | Path |
|------|---------|------|
| application.log | Exceptions (details incl. trace — logs only) | `<app_root>/storage/logs/application.log` |
| api.log | Per-request method/endpoint/status/duration | `.../api.log` |
| security.log | Auth failures, denials, rate-limit hits, lockouts | `.../security.log` |
| notification.log | Push/SMS send/retry/fail (masked) | `.../notification.log` |
| cron.log | Cron stdout/stderr (from cron redirect) | `.../cron.log` |
| translations_missing.log | Missing translation keys (throttled) | `.../translations_missing.log` |

CLI tail helper:
```bash
tail -n 100 storage/logs/application.log
```
Log viewer in admin portal (phase 07/15) shows audit; log-file rotation is manual
until a `rotate-logs` job exists (see `docs/35-cron-jobs.md` note). Suggested host
cron to keep files bounded:
```bash
0 3 * * * find <app_root>/storage/logs -name '*.log' -size +1M -mtime +1 -exec gzip {} \; 2>/dev/null
```

## 11. Security Headers (live check)

```bash
curl -I https://<domain>/
# expect: X-Content-Type-Options: nosniff
#         X-Frame-Options: SAMEORIGIN
#         Referrer-Policy: strict-origin-when-cross-origin
#         Content-Security-Policy: ... upgrade-insecure-requests ...
#         Permissions-Policy: geolocation=(), microphone=(), camera=()
#         (Strict-Transport-Security only after https verified)
```
`.env*`/`*.log`/config dirs denied by `.htaccess` (`public/.htaccess`).

## 12. Deployment Commands (quick reference)

```powershell
# Windows dev -> host (see deploy/sync.ps1 first!):
powershell -ExecutionPolicy Bypass -File deploy\sync.ps1 -DryRun   # preview
powershell -ExecutionPolicy Bypass -File deploy\sync.ps1           # run
```
```bash
# Host-side oneliner (cPanel/Plesk terminal):
cd /home/user/fps && php app/console/migrate.php run && php app/console/seed.php && php app/console/migrate.php status
```

## 13. Shared hosting day-1 (testing.deepjyotimicrofinance.com)

Live deployment: **`https://testing.deepjyotimicrofinance.com`** — cPanel shared LAMP,
**File Manager only (no SSH)**, **subdomain-folder = docroot layout** (app + web entry
share the folder; denied via `.htaccess`).

| Item | Value (replace `<USER>` with the cPanel username) |
|------|---------------------------------------------------|
| Subdomain docroot (webroot) | The cPanel subdomain folder **itself** — everything placed inside `testing.deepjyotimicrofinance.com/` is served directly (there is **no** separate `public_html` sub-folder inside it). |
| Full folder on disk | `/home/<USER>/public_html/testing.deepjyotimicrofinance.com/` (or `/home/<USER>/testing.deepjyotimicrofinance.com/` — check what the panel shows). |
| `.htaccess` | `<docroot>/.htaccess` == `deploy/htaccess.production` — forces HTTPS, denies `app/` `config/` `storage/` `.env` `*.log` via both `RewriteRule` + Apache 2.4 `Require all denied` (`FilesMatch`). |
| `index.php` | `<docroot>/index.php` — uses a `__DIR__` fallback so it finds `app/` in its own directory. |
| `.env` | `<docroot>/.env`, `chmod 600` — created manually, never uploaded; sits inside the webroot (denied by `.htaccess`; use **only** if the panel never offers a custom docroot). |
| DB (created in cPanel) | `<USER>_fps_testing` / user `<USER>_fps_test`, host `localhost` |
| PHP for subdomain | host default + `pdo_mysql openssl curl mbstring` |
| PHP INI overrides | `upload_max_filesize=20M`, `post_max_size=24M`, `memory_limit=256M`, `max_execution_time=180`, `display_errors=Off`, `date.timezone=Asia/Kolkata` |
| Cron (6 jobs) | `deploy/checklist.md` §8 — schedule per §5 above, bin `<PHP_BIN>` (find `/usr/local/bin/php` or `/usr/bin/php`) |
| Uploads location | `<docroot>/storage/app/private/files` — inside the webroot but uploads are stored in `storage/` (denied by `.htaccess`) and served via PHP controllers (never directly web-executed) |
| Health/monitoring URL | `https://testing.deepjyotimicrofinance.com/health` |

**Day-1 gotchas for a File-Manager-only single-folder host:**
1. **Everything lives inside the webroot.** `.htaccess` deny-rules are the only barrier between
   `app/`, `.env`, `storage/` and the public internet. These rules work on Apache 2.4 (cPanel
   default); verify by fetching `https://testing.deepjyotimicrofinance.com/.env` → 403/404.
   If that URL returns anything other than an error, escalate to host support immediately.
2. `.htaccess` is a dotfile — File Manager hides it unless **Settings → Show Hidden Files** is on.
3. No terminal → run `migrate run` / `seed.php` via **one-off cron entries** (§ `deploy/checklist.md` §7)
   or ask host support to execute the two CLI commands.
4. If the cron output says `command not found: php`, switch `<PHP_BIN>` between
   `/usr/local/bin/php` and `/usr/bin/php` (no SSH to probe `which`).
5. `.env` must be created after files are uploaded (it is intentionally **not** in the deploy zip);
   permissions 640/600.
6. HSTS stays commented (`.htaccess`) until HTTPS is stable for ~1 week.
7. Storage dirs need **775** (`logs/`, `cache/`, `temp/`, `app/private/files`) so PHP writes under suPHP.
8. Backups live at `/home/<USER>/backups/` — never inside the app webroot.

**Day-1 checklist (this deployment):** follow `deploy/checklist.md` (steps §14 in order —
subdomain → php version → DB → INI → upload → .env → migrate/seed → cron → SSL → smoke).
This sub-menu references the canonical §1–§12 of this runbook for incident/backup handling.

---

**Review cadence**: runbook drills monthly; update this file after every incident.