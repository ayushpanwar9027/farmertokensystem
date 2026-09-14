# Deploy Checklist — testing.deepjyotimicrofinance.com

**Deployment:** Farmer Procurement System | **Panel:** cPanel | **Access:** File Manager only (NO SSH)
**Layout:** subdomain folder = docroot (everything lives in one folder, denied by .htaccess) | **Git:** repo is not git-initialised locally; secrets handled manually.
**Prepared:** 2026-09-12 | **Status key:** `[DO]` = do it now · `[HUMAN]` = needs you/panel · `[[VERIFY]]` = record the result

> **REVIEW GATE — read this file, confirm the layout/paths with the AI, then perform the
> step sequence at §14 at the end. Do not touch the host before §0/§1 are signed.**

---

## 0. Layout decision (cPanel subdomain folder = docroot)

Your cPanel serves the **subdomain folder directly** — everything you put inside
`testing.deepjyotimicrofinance.com/` is web-hosted (there is **no** `public_html`
sub-folder inside it). So the app **and** the web entry share the same folder:

```
/home/<USER>/
└── public_html/                                (main domain docroot, untouched)
    └── testing.deepjyotimicrofinance.com/      <- SUBDOMAIN DOCROOT (web root)
        ├── index.php                           <- web entry (front controller)
        ├── .htaccess                           <- == deploy/htaccess.production
        ├── portal/                             <- admin portal (web-served)
        ├── README-DEPLOY.txt
        ├── app/                                <- app code
        ├── config/                             <- config files
        ├── database/                           <- migrations/, seeders/
        ├── resources/
        ├── storage/                            <- logs/ cache/ temp/ app/private/files (writable 775)
        ├── docs/
        └── .env                                <- created in step 6; chmod 600/640
```

**Because the folder is the web root**, `index.php` had to be adjusted so it finds
`app/` in its **own** directory (fallback keeps the old `public/`-sibling layout
working too). The `.htaccess` (front controller) now also sits at the zip root and
is responsible for:

- serving `portal/` and routing `/api/v1/*`, `/health`, `/health/maintenance` → `index.php`
- forcing HTTPS
- **blocking web access** to `app/`, `config/`, `database/`, `storage/`, `resources/`,
  `scripts/`, `.env*`, `*.log` — even though they sit inside the web root.

Security note: ideal architecture keeps secrets OUTSIDE the web root, but on this
panel you cannot — so `.env` must stay `600`, the deny rules above stay active, and
if the panel ever offers a **custom document root**, use the Option A/B layout from
`docs/36-shared-hosting.md` instead (the `index.php` fallback still supports it).

**Placeholders to fill in:**
- `<USER>` = your cPanel username. Find it in cPanel → File Manager (home folder name) or the
  left-hand "Account" menu. Record: **`<USER> = __________`**
- `<PHP_BIN>` = host PHP CLI path (**only needed if you later add cron jobs / use host-support
  fallback**). Candidates: `/usr/local/bin/php` or `/usr/bin/php` — pick by testing with a
  one-off cron, or ask host support.

---

## 1. Pre-deploy checks (local + backup — `[DO]` before touching the host)

| # | Check | Evidence | Result |
|---|-------|----------|--------|
| 1.1 | Deploy zip built and scanned, no secrets | `deploy/fps_deploy_testing.zip` (0.55 MB, 362 entries). Listcheck: no `.env`, no `_test.php`, no `rbac_*`, no `*.zip`, no `scripts/` | ✅ |
| 1.2 | `.htaccess` canonical copy in place on host | `deploy/htaccess.production` ≡ `public/.htaccess` (verified identical) | ✅ |
| 1.3 | `.env.production` template exists, **no real values** | gitignored via `.gitignore` (`.env.production`) | ✅ |
| 1.4 | **``**[HUMAN]**``** Baseline backup of the subdomain folder's default page | cPanel → **Backup** (or File Manager → zip the small existing contents of `testing.deepjyotimicrofinance.com/`) if a default page exists | ❌ |
| 1.5 | Live OTP gateway + OneSignal **LIVE** (prod) credentials ready | provider dashboards (values only ever entered in host `.env`, §5) | ❌ |
| 1.6 | Main domain (`deepjyotimicrofinance.com`) resolves + SSL present | `https://deepjyotimicrofinance.com` loads | ❌ [[VERIFY]]

---

## 2. Panel step 1 — Subdomain + PHP per directory — `[HUMAN]`

1. cPanel → **Domains → Create A New Domain** (or **Subdomains**).
   - Subdomain: **`testing`** (parent: `deepjyotimicrofinance.com`).
   - Resulting URL: **`https://testing.deepjyotimicrofinance.com`**.
   - On YOUR cPanel the subdomain folder **`testing.deepjyotimicrofinance.com`** is itself the
     docroot (whatever is inside gets served). Record the exact folder the panel shows:
     **____________________** (typically `public_html/testing.deepjyotimicrofinance.com/` or
     `~/testing.deepjyotimicrofinance.com/`). The whole app deploys INTO this folder.
2. Let DNS settle: `nslookup testing.deepjyotimicrofinance.com` (cPanel usually auto-adds the A record). `[[VERIFY]]`
3. cPanel → **Software → MultiPHP Manager**: set the subdomain folder to the **host default** PHP
   (you chose "Use host default"); verify these extensions are active: `pdo_mysql`, `openssl`,
   `curl`, `mbstring` (required by code); `fileinfo`, `gd`, `zip`, `exif` optional. List them on the
   next page of MultiPHP if needed. `[[VERIFY]]`
4. Baseline backup of the subdomain folder's default page (only if a default page exists) — cPanel → Backup. `[[VERIFY]]`
5. Cron Jobs: skip for now — created at §7 (needs the app uploaded first).

## 3. Panel step 2 — Database — `[HUMAN]`

1. cPanel → **MySQL® Databases**.
2. Create database: **`<USER>_fps_testing`** (cPanel prefixes `<USER>_`; record the FULL name: `_______________`).
3. Create user: **`<USER>_fps_test`** (full name `_______________`) with a **strong, unique** password generated locally (password manager). Record → `deploy/credentials.local.md` (gitignored, NEVER in chat).
4. Add user to database → **ALL PRIVILEGES** (on this one DB only).
5. Confirm **MySQL Hostname** = `localhost` (`[[VERIFY]]` — most shared hosts; if different, use it in `.env`).
6. Validate: In phpMyAdmin run `SELECT VERSION();` → ≥ 8.0 recommended; charset `utf8mb4`. `[[VERIFY]]`

## 4. Panel step 3 — PHP INI overrides — `[HUMAN]`

cPanel → **Software → MultiPHP INI Editor** (subdomain scope). Apply and record:

| Directive | Value | Applied |
|-----------|-------|---------|
| `upload_max_filesize` | `20M` | ❌ |
| `post_max_size` | `24M` | ❌ |
| `memory_limit` | `256M` | ❌ |
| `max_execution_time` | `180` | ❌ |
| `display_errors` | `Off` | ❌ |
| `log_errors` | `On` | ❌ |
| `error_reporting` | `E_ALL & ~E_DEPRECATED` | ❌ |
| `date.timezone` | `Asia/Kolkata` | ❌ |
| `opcache` | `enabled` (if available; `validate_timestamps=1`, `revalidate_freq=0`) | ❌ |

> Keeps in sync with `.env`'s `FILE_UPLOAD_MAX_SIZE=20971520` (20 MB) so uploads match.

## 5. Panel step 4 — Upload via File Manager — `[HUMAN]`

1. cPanel → **File Manager** → open the subdomain folder **`testing.deepjyotimicrofinance.com`**
   (your cPanel serves this folder directly — this IS the webroot).
2. **Upload** `deploy\fps_deploy_testing.zip` (from this repo, dev machine).
3. **Extract** `fps_deploy_testing.zip` **IN THAT SAME FOLDER** (in place).
   No moving/copying is required — the zip is already laid out for your docroot:
   `index.php`, `.htaccess`, `portal/` are beside `app/`, `config/`, `storage/` …
   (Enable **Settings → Show Hidden Files** so `.htaccess` shows.)
   The zip also contains **`run-deploy.php`** (one-time migrate+seed helper — used in §7,
   then DELETED).
4. Permissions (File Manager → right-click → **Change Permissions**, apply recursively):
   - All files `644`, all dirs `755`.
   - `storage/` and everything under it (`storage/logs`, `storage/cache`, `storage/temp`,
     `storage/app/private/files`) → **775** (owner+group writable) so PHP writes logs/uploads under suPHP.
   - Verify `testing.deepjyotimicrofinance.com/.htaccess` exists (dotfile!). `[[VERIFY]]`
5. Confirm there is **NO `.env`** anywhere yet (created in step 6). `[[VERIFY]]`
6. Quick sanity: open `https://testing.deepjyotimicrofinance.com/portal/` → if it renders,
   everything is wired (PHP + .htaccess + portal). If 500 → check PHP version/extensions (§2).

## 6. Panel step 5 — Create `.env` on the host — `[HUMAN]` (secrets never in chat)

1. File Manager → **`testing.deepjyotimicrofinance.com/`** root.
2. **Copy** `public/../README`… no — copy the local template **`.env.production`** you'll create on
   the host manually: File Manager → **+ File** → name it **`.env`** → Edit → paste the contents of
   the repo's **`.env.production`** → save. (Template has ONLY placeholders; NO real data.)
3. Replace the `REPLACE_*` values **directly in the host file**:
   - `APP_URL=https://testing.deepjyotimicrofinance.com` (already set), `APP_ENV=production`, `APP_DEBUG=false`.
   - `DB_*`: DB name/user/password + host from §3.
   - `APP_SECRET`, `JWT_SECRET`, `ENCRYPTION_KEY`: generate fresh (not copied from dev). Save ENCRYPTION_KEY to your password manager (unrecoverable if lost).
   - `ONESIGNAL_APP_ID` + `ONESIGNAL_REST_API_KEY`: **LIVE** OneSignal app (§1.5).
   - `OTP_API_KEY` / `OTP_SENDER_ID` / `OTP_TEMPLATE_ID`: **LIVE** gateway (§1.5).
   - `CORS_ALLOWED_ORIGINS=https://testing.deepjyotimicrofinance.com` (already set; keep it).
4. Permissions: right-click `.env` → **640** (owner:group read, no others) — if File Manager allows 600, use it.
5. NEVER paste any of these values into chat/logs. `[[VERIFY]]` — confirm no secrets were transmitted to the AI.

## 7. Migrate + non-demo seed — `[HUMAN]`, no SSH → run via browser URL

There is **no terminal** and **no cron needed for this step**. The zip ships
**`run-deploy.php`** so you just hit one URL:

1. Open in the browser (after §6 has created `.env`; `run-deploy.php` refuses to
   run if `.env` is missing):
   ```
   https://testing.deepjyotimicrofinance.com/run-deploy.php?confirm=YES
   ```
2. It runs **migrate** then the **idempotent non-demo seed** (roles 5, permissions
   61→, languages 2, settings 48, secrets 7, districts 5, crops 16, rates 16) and
   prints `=== DONE ===`. **It refuses a 2nd run** (writes a `run-deploy.done` marker).
   - `seed.php` is idempotent and never runs `--demo` / `--reset` in this flow.
3. **IMMEDIATELY DELETE these two files** via File Manager (security):
   - `run-deploy.php`
   - `run-deploy.done`
4. Verify (phpMyAdmin):
   ```
   SELECT COUNT(*) FROM migrations;                 -- all applied
   SELECT COUNT(*) FROM users;                      -- 0 (no demo)
   SELECT COUNT(*) FROM system_settings;            -- 48
   SELECT COUNT(*) FROM districts;                  -- 5
   ```
5. If the page errors (500/timeout): re-check `.env` values (§6), DB grants (§3),
   PHP version (§2), then re-upload `run-deploy.php` and retry. `[[VERIFY]]`

> Re-running later (new migrations added): just re-upload `run-deploy.php` from
> `deploy/` on your dev machine, hit the URL again, then delete it again.

### §7.1 TESTING subdomain only — add demo showcase data + login accounts — `[HUMAN]`

`run-deploy.php` creates **no user accounts** (only roles/permissions/settings). To
log in on the **testing subdomain** and see the full SIH showcase UI, the demo
helper is included in the zip:

1. After §7 completes, hit:
   ```
   https://testing.deepjyotimicrofinance.com/run-demo.php?confirm=YES
   ```
2. Output prints all demo credentials. **DEMO PASSWORDS ARE PUBLICLY KNOWN.**
3. **DELETE immediately:** `run-demo.php` + `run-demo.done`.
4. **NEVER run `run-demo.php` on the production subdomain** (set a separate .env
   + separate DB, and run only `run-deploy.php` there).

| Role | Login | Password | Portal URL |
|------|-------|----------|------------|
| **Super Admin** | `demo_super_admin` | `Admin@1234` | `/portal/` |
| **Manager** | `demo_manager` | `Admin@1234` | `/portal/` |
| **Operator** | `demo_operator` | `Admin@1234` | `/portal/` |
| **Farmer** | mobile `9000000001`..`9000000015` | `Demo@1234` | (mobile app only) |

`[[VERIFY]]` — login as `demo_super_admin`, confirm dashboard loads, navigate pages.

After login:
- **Change the default password** (top-right name menu → *Change Password*).
- **Create farmers with passwords**: *Administration → Farmers → + Farmer* — enter
  name, mobile, village, district and a password (or *Generate*). The farmer then
  logs into the **mobile app** directly with that mobile + password. New farmer is
  auto-approved/ACTIVE. Password can be reset anytime via the *Password* action.
- **Create staff**: *Administration → Staff → + Staff* (staff set their own password
  via the forgot-password flow).

**Mobile app (`useitnow.apk`)** — built separately and already pointed at
`https://testing.deepjyotimicrofinance.com` (flavour-independent default in
`farmer_app/lib/config/env.dart` + `api_constants.dart`); release APK renamed to
`useitnow.apk`. Farmer login = mobile + password (created by admin, or demo
`9000000001..9000000015` / `Demo@1234`).

**Backup option (request host support)** — if the URL helper ever fails:
```
cd /home/<USER>/testing.deepjyotimicrofinance.com && php app/console/migrate.php run && php app/console/seed.php
```
**Local cross-check `[AI]`:** run `php app/console/migrate.php status` locally and compare the
file list/batch numbers with what §7 reports on the host — they must match exactly.

## 8. Cron jobs (permanent) — `[HUMAN]` — DEFER: currently optional, not needed to launch

cPanel → **Cron Jobs**. Absolute same style as §7; replace `<PHP_BIN>` and `<USER>`; logs to
`<root>/storage/logs/cron.log` (same as the app logs, outside webroot).

```
15 0 * * *  <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=generate-slots >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
*/5 * * * * <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=expire-pending-bookings >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
*/30 * * * * <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=expire-unarrived-bookings >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
*/5 * * * * <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=queue-notify >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
* * * * *   <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=send-pending-notifications >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
*/5 * * * * <PHP_BIN> /home/<USER>/testing.deepjyotimicrofinance.com/app/console/cron.php --job=retry-notifications >> /home/<USER>/testing.deepjyotimicrofinance.com/storage/logs/cron.log 2>&1
```

Post-verify (`[[VERIFY]]`): each job's audit row appears — search `audit_logs` for
`reason` = `cron_*` (SLOTS_GENERATED, BOOKINGS_EXPIRED, QUEUE_NOTIFY, SEND_PENDING_NOTIFICATIONS,
RETRY_NOTIFICATIONS). Schedules match `docs/35-cron-jobs.md`.

## 9. Panel step 7 — SSL — `[HUMAN]`

1. cPanel → **SSL/TLS Status** → run **AutoSSL**/issue **Let's Encrypt** for
   `testing.deepjyotimicrofinance.com` (+ `www` prefix optional).
2. HTTPS is already forced by the `.htaccess` in the subdomain folder (301). Verify: `[[VERIFY]]`
   - `http://testing.deepjyotimicrofinance.com` → **301** → `https://…`
   - `https://…/health` → 200 JSON `"status":"UP"`.
3. Security headers on https: `curl -I https://testing.deepjyotimicrofinance.com/` shows
   `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP. `[[VERIFY]]`
4. **After ~1 week confirmed stable**: uncomment the HSTS line in the subdomain folder's `.htaccess`
   (keep it commented until then).

## 10. Panel step 8 — Post-deploy smoke (production subdomain) — `[HUMAN]` + `[AI]`

Use **only a throwaway test mobile** (never real data). Record each PASS/FAIL `[[VERIFY]]`:

| # | Check | Command/URL | Result |
|---|-------|-------------|--------|
| 10.1 | Health | `GET /health` → 200 UP (DB + storage OK) | ❌ |
| 10.2 | Maintenance status | `GET /health/maintenance` → `enabled:false` | ❌ |
| 10.3 | HTTPS redirect | `http://…` → 301 → https | ❌ |
| 10.4 | Test routes REMOVED on prod | `POST /api/v1/test/echo` → 404 (APP_ENV=production) | ❌ |
| 10.5 | Portal login page | `https://…/portal/` → login renders; JS/CSS 200; no console errors | ❌ |
| 10.6 | Portal locale | en ↔ हिन्दी toggle works | ❌ |
| 10.7 | Staff login + 2FA | real OTP SMS to test mobile | ❌ |
| 10.8 | Full cycle (test centre only) | slot → booking → queue → procurement → payment release | ❌ |
| 10.9 | Push | LIVE OneSignal test push received on test phone | ❌ |
| 10.10 | Smoke cleanup | delete ONLY the throwaway test user + its rows (transaction); re-verify seed counts pristine | ❌ |

Optional now: release APK with `--dart-define=API_BASE=https://testing.deepjyotimicrofinance.com`
(`farmer_app`, Phase-18 STEP I) — note in `docs/PROJECT-STATE.md` when done.

## 11. Monitoring + backups — `[HUMAN]`

- Health URL for monitoring: **`https://testing.deepjyotimicrofinance.com/health`**.
- Backups live at **`/home/<USER>/backups/`** (NOT in the app/webroot), scheduled as cron,
  DB nightly 02:00 + files 02:30, retention 30 daily / 12 monthly, off-site weekly (`docs/OPS-RUNBOOK.md` §6).
- **Take the FIRST backup NOW** (before any more changes) and verify the `.sql` starts with
  schema (inspect only, no secrets): `gzip -t` + `head -n 5` of the dump.
- **Test restore is a SEPARATE later step** — restore into a scratch DB/dir only, never over live
  (procedure: `docs/OPS-RUNBOOK.md` §7).

## 12. Rollback (if a smoke check fails at any point)

1. `.htaccess` → restore the previous copy (or re-apply `deploy/htaccess.production`).
2. `.env` → restore from `backups/files/*.tar.gz` (contains `.env` at 600).
3. DB → restore last good dump: `gunzip -c backups/db/db_<date>.sql.gz | mysql …` (scratch-first).
4. Cron → delete the new §8 entries until verified.
5. Files/webroot → re-upload the previous contents of the subdomain folder.
6. Toggle **Maintenance ON** (`portal → Maintenance`) while rolling back.
7. Re-run health + login + one-booking smoke before reopening.
**Rule:** on any failure → fix → restart from the failed step. Never skip ahead.

## 13. Ops docs (day-1) — delivered

- `docs/OPS-RUNBOOK.md` — new **§ "Shared hosting day-1"** section added (this deployment).
- `docs/ONBOARDING.md` — farmer/staff onboarding (unchanged guide + prod URL pointer).
- `docs/PROJECT-STATE.md` — env matrix now lists this production URL.
- `deploy/sync.sh` (SSH rsync path, future) + `deploy/make_deploy_zip.ps1` (zip rebuild) + `deploy/fps_deploy_testing.zip` (current artifact).

## 14. THE ORDERED HOST SEQUENCE (do in this order, nothing skipped)

| Step | What | Where | Exact value/path |
|------|------|-------|------------------|
| **1** | Create subdomain `testing` | cPanel → Domains | serves folder `testing.deepjyotimicrofinance.com` directly (docroot = the folder itself) |
| **2** | PHP version + extensions for subdomain dir | MultiPHP Manager | host default; ensure `pdo_mysql openssl curl mbstring` visible |
| **3** | Database + user + ALL privileges | MySQL Databases | `<USER>_fps_testing` / `<USER>_fps_test`, host `localhost` |
| **4** | PHP INI values | MultiPHP INI Editor | 20M/24M/256M/180/Off/On/E_ALL & ~E_DEPRECATED/Asia/Kolkata |
| **5** | Upload + extract zip IN the subdomain folder (no moving) | File Manager | extract `fps_deploy_testing.zip` into `testing.deepjyotimicrofinance.com/`; perms 644/755/775 |
| **6** | Create `.env` (from template, real LIVE keys) | File Manager | `.env` in the subdomain folder, chmod 640/600; DB + secrets per §3/§6 |
| **7** | Migrate + non-demo seed | **browser URL** | `https://testing.deepjyotimicrofinance.com/run-deploy.php?confirm=YES` → then **delete** `run-deploy.php` + `run-deploy.done`; §7 |
| **8** | Cron jobs (6) — **currently optional / deferred** | Cron Jobs (later) | §8 commands with `<PHP_BIN>` + `<USER>`; add when needed |
| **9** | SSL (AutoSSL/Let's Encrypt) + verify https/headers | SSL/TLS Status | `testing.deepjyotimicrofinance.com`; HSTS stays commented |
| **10** | Smoke (throwaway phone) | browser + curl | §10 table; clean up test user after |

---

**Sign-off:** by reviewing this file with the AI and confirming §0/§1, you agree to the
single-folder docroot layout and the sequence above. Any divergence (panel shows a different
folder structure, php binary missing, AutoSSL unavailable) → stop and re-open this checklist
with the AI before continuing.