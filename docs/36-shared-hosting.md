# Shared Hosting Compatibility

## Overview

The system must run on typical Linux shared hosting (cPanel/Plesk): Apache + PHP 8.x + MySQL + cron. This document records compatibility decisions.

## Constraints of Shared Hosting

| Constraint | Impact | Mitigation |
|------------|--------|-----------|
| No root access | Can't install system packages | Pure PHP + MySQL only |
| No Redis | No in-memory cache/store | File/DB-based sessions, cache, rate limits |
| No queue workers | Can't run persistent workers | Cron-based jobs |
| No WebSockets | No real-time push | AJAX polling |
| Shared CPU/RAM | Limited concurrency | Efficient queries, indexing, pagination |
| MySQL limits | DB size, connections | Lean schema, archives, backups |
| No Docker/K8s | Container deploys not needed | Manual PHP deploy |
| Apache only | Routing via mod_rewrite | .htaccess rules |

## What This Means for Architecture

- **Sessions**: DB-backed (user_sessions) — portable, not file-based
- **Cache**: file-based or none; settings cached in files with invalidation
- **Rate limiting**: file/DB-based
- **Async**: no real queues; use cron + notification_logs statuses
- **Real-time**: polling (5s) not WebSockets
- **Storage**: local filesystem via StorageService abstraction
- **Cron**: cPanel cron → `php app/console/cron.php`

## PHP Extensions Required

- `pdo_mysql` (PDO MySQL)
- `openssl` (encryption)
- `mbstring` (multibyte/Devanagari)
- `json` (always on)
- `curl` (OneSignal/OTP gateway HTTP, external URLs)
- `fileinfo` (upload MIME)
- `session` (web sessions)
- `intl` (optional, for date formatting)
- `gd` (optional, image processing)

## Directory Placement (Security)

```
/home/user/                  # account root
├── public_html/             # web root ONLY
│   ├── index.php            # entry
│   ├── .htaccess
│   └── portal/              # static admin portal
├── app/                     # NOT web-accessible
├── config/
├── storage/                 # logs/uploads/cache (writable)
├── database/
├── .env                     # chmod 600, outside web root
└── backups/
```

- Ensure app/, config/, storage/, database/, .env are OUTSIDE `public_html`
- If forced to keep inside, deny via .htaccess

## .htaccess Essentials (public_html)

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route API to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^api/ index.php [QSA,L]

# Security headers (see security doc)
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### Protect non-public dirs (if under public_html)
```apache
RewriteRule ^(app|config|storage|database|vendor)(/|$) - [F,L]
```

## MySQL on Shared Hosting

- Usually MySQL 8.0+ or MariaDB 10.x
- Single DB user; connection over localhost socket
- Database size limits (often 1-2 GB) — keep lean, archive audit logs
- Max connections shared — keep connections short (no persistent)

## Cron via cPanel

- Use full paths to php binary: `/usr/bin/php`
- Direct output to temp logs to avoid email spam
- Frequency per job table ([35-cron-jobs.md](35-cron-jobs.md))

## PHP Settings That May Be Restricted

- `memory_limit` often 128M — fine
- `max_execution_time` manageable (cron may allow longer via CLI)
- `open_basedir` may restrict to account root — fine (all under /home/user)
- `register_globals` off (default)
- `display_errors` off in production

## SSL/HTTPS

- Hosting provides free SSL (Let's Encrypt / autoSSL)
- Force HTTPS via .htaccess
- HSTS header set (careful: ensure HTTPS fully working first)

## Storage Limits & Monitoring

- Watch inode/quota usage (uploads accumulate)
- Cleanup temp + logs + backups retention
- File manager enforces size limits

## Deployment Without CI/CD

Manual deployment ([39-deployment.md](39-deployment.md)):
1. FTP/SFTP/git-pull-on-server (careful with credentials)
2. Upload changed files
3. Run migrations via CLI
4. Set permissions
5. Test

## Cost Consideration

- Shared hosting ~₹1000-3000/yr — SIH budget friendly
- Upgrade path: shared → VPS (LAMP) → cloud objects → Redis (future) ([37])

## Design for Migration Later

Because everything is abstracted (Storage, Notification, Settings, Permission), upgrading to VPS/cloud does not require rewrite:
- Swap StorageAdapter → S3
- Add Redis cache/rate-limit impl
- Move sessions to Redis
- Add real message queue (optional)

---

**Next**: [37-scaling-strategy.md](37-scaling-strategy.md) for scaling.