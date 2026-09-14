# Deployment

## Overview

Manual deployment to Linux shared hosting (Apache + PHP + MySQL). No CI/CD.

## Deployment Architecture

```
Local development              Production (shared hosting)
┌──────────────────┐           ┌──────────────────────────────┐
│ XAMPP + code      │  FTP/SFTP │ public_html/                 │
│ source folder     │ ────────▶ │   index.php, .htaccess, portal│
│ database/         │           │ app/ (outside web root)       │
│ migrations        │           │ config/, storage/, .env      │
│ Flutter project   │  build    │ MySQL database                │
└──────────────────┘           └──────────────────────────────┘
```

## Pre-Deployment Checklist

1. Code finalized for release (no debug endpoints)
2. Production `.env` configured (APP_ENV=production, DEBUG=false, DB creds, JWT_SECRET, ENCRYPTION_KEY)
3. `.env` permissions 600; outside web root
4. Migrations prepared (versioned)
5. Demo seed NOT run in production (or run restricted seed for roles/permissions/settings/languages)
6. Backup available

## Deployment Steps (Initial)

```
1. Prepare files
   - Upload app/, config/, database/, storage/, .env, public/ to server
   - Ensure storage/ writable (chmod 775 or 777 as needed on logs/cache)
   - Ensure .env NOT in web root

2. Upload web root
   - public/index.php, .htaccess, portal/ → public_html/

3. Database
   - cPanel → MySQL Databases → create DB + user, grant all
   - Run migrations: php app/console/migrate.php
   - Run seed (roles/permissions/settings/languages): php app/console/seed.php
   - Import districts (initial data)

4. Configure cron
   - Add cron jobs per [35-cron-jobs.md]

5. SSL
   - Enable SSL (Let's Encrypt/host)
   - Force HTTPS via .htaccess

6. Smoke test
   - GET /health → UP
   - Login admin → portal loads
   - Test OTP SMS + OneSignal test push (if configured)
   - Register a test farmer (OTP)
```

## Update Deployment Steps (Incremental)

```
1. Backup prod DB + files
2. Upload changed files (respect directory placement)
3. Run new migrations if any: php app/console/migrate.php
4. Run seed additions if any (idempotent)
5. Clear caches (storage/cache settings)
6. Smoke test critical flows
7. Update PROJECT-STATE (deployment/version)
```

## Environment-Specific Notes

### Local (XAMPP)
- `php` CLI available
- MySQL via phpMyAdmin
- APP_ENV=local for dev (display_errors on for debugging)

### Staging (subdomain)
- APP_ENV=staging (errors logged, not shown)
- Separate DB, demo seed for integration tests

### Production
- APP_ENV=production
- display_errors=Off
- HTTPS enforced

## .env Template

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_SECRET=<random>

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<dbname>
DB_USERNAME=<dbuser>
DB_PASSWORD=<dbpass>

JWT_SECRET=<random long>
ENCRYPTION_KEY=<256-bit hex>

SESSION_LIFETIME=1800
REMEMBER_ME_EXPIRY=2592000

RATE_LIMIT_LOGIN=5
RATE_LIMIT_OTP=3
RATE_LIMIT_BOOKING=10
RATE_LIMIT_SMS=10

FILE_UPLOAD_MAX_SIZE=5242880
NOTIFICATION_MAX_RETRIES=3
NOTIFICATION_RETRY_INTERVAL=300

APP_TIMEZONE=Asia/Kolkata
```

## File Permissions

| Path | Perm |
|------|------|
| public_html/ | 755 |
| index.php | 644 |
| app/, config/, database/ | 755 dirs, 644 files |
| storage/logs | 775 writable |
| storage/cache, temp | 775 writable |
| .env | 600 |
| backups/ | 755 (writable) |

## Backup Before Deploy

- `php app/console/cron.php --job=backup-database` (or manual mysqldump)
- Files copied before overwriting

## Rollback

- Restore previous `.env`
- Restore previous files (backup)
- Restore DB from backup if migrations broke data
- Disable new cron entries

## Verification After Deploy

- `/health` UP checks DB/storage
- `/health/maintenance` OFF
- Login works (Super Admin)
- One full flow: farmer register (OTP) → verify → login → book → token → queue → procurement → payment (staging/demo or real)
- Cron `cron_runs` shows OK after first cycle
- Logs present: application.log, api.log, security.log, notification.log

## Monitoring Enablement

- Ensure `monitor-health` cron runs every 5 min
- Check monitoring_events for critical alerts
- Confirm OTP test SMS + OneSignal test push work (credentials set)

## Flutter App Deployment

- Build APK: `flutter build apk --release`
- Android-only for SIH
- Install via APK / Play Store (optional)
- API base URL in release build → production URL
- Version bump + package metadata

## Portal Deployment

- Pure static files → upload to public_html/portal/
- No build step
- API base URL configured in portal app.js (production)

## Maintenance-Window Deploys

When deploying breaking changes:
1. Enable maintenance mode (Super Admin)
2. Deploy + migrate
3. Smoke test
4. Disable maintenance
5. Notify users if applicable

## Post-Launch Operational Tasks

- Weekly: verify backups restore (test restore monthly)
- Daily: check cron_runs + logs
- Monitor: error_rate, failed logins, SMS failures
- Update PROJECT-STATE with deploy version/date

---

**All core docs complete.** Next: phase files (phase-00 → phase-18).