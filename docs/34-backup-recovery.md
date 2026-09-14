# Backup & Recovery

## Overview

A backup plan is incomplete unless restoration is documented and tested. This covers database + file backups, retention, restore procedure, and verification.

## Backup Scope

1. **Database** (MySQL dump) — all tables
2. **Files** — storage/app (uploads), config (.env), storage/cache as needed
3. **Application code** — typically re-deployable; include in backup for completeness

## Backup Strategy

### Database Backup (nightly)
```
mysqldump -u fps_user -p'...' --single-transaction --routines farmer_procurement \
  | gzip > /home/user/backups/db/db_$(date +%Y%m%d_%H%M%S).sql.gz
```
- `--single-transaction` for consistent dump (InnoDB)
- Compress with gzip
- Encrypt optional: `openssl enc -aes-256-cbc -salt -in ... -out ...` (with key stored apart)
- Verify file: `gzip -t backup.sql.gz`

### File Backup (nightly)
- rsync or tar of storage/app, storage/cache (excluding logs optionally)
- `tar -czf /home/user/backups/files/files_$(date).tar.gz -C /home/user storage/app storage/cache`
- Also copy `.env` and config

### Off-site / Secondary
- Download key backups to local/secure secondary storage periodically (manual or hosting auto-backup)
- Document where off-site copies keep (e.g., personal drive, secondary server)

## Retention Policy

| Backup | Frequency | Retention |
|--------|-----------|-----------|
| Database | Daily | 30 days |
| Database (monthly) | 1st of month | 12 months |
| Files | Daily | 30 days |
| Files (monthly) | 1st of month | 12 months |
| .env/config | On change + daily | 12 months |

## Restore Procedure (Documented)

### Database Restore
```bash
# 1. Confirm backup file integrity
gzip -t /home/user/backups/db/db_20260908.sql.gz

# 2. Optionally create fresh DB or use existing
mysql -u fps_user -p'...' -e "CREATE DATABASE farmer_procurement CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. Import
gunzip -c db_20260908.sql.gz | mysql -u fps_user -p'...' farmer_procurement

# 4. Verify
mysql -u fps_user -p'...' -e "SELECT COUNT(*) FROM users; SELECT MAX(created_at) FROM audit_logs;"
```

### File Restore
```bash
tar -xzf backups/files/files_20260908.tar.gz -C /home/user/
# verify permissions on storage/logs (writable)
```

### Config Restore
- Restore .env from backup
- Verify `ENCRYPTION_KEY` matches the DB secrets (if DB restored from same era)

### Post-Restore Verification Checklist
1. Health endpoint returns UP
2. Can log in (admin + test farmer)
3. Recent bookings/procurements visible
4. OTP test SMS + OneSignal test push work (credentials intact)
5. Maintenance mode OFF
6. Cron runs normally

## Backup Verification

- Daily automated `gzip -t` check on latest backup
- **Monthly test restore** to a scratch database (documented, manual) 
- Compare row counts / max IDs to production
- Record verification results in PROJECT-STATE or admin log

## Cron Integration

Cron jobs for backup ([35-cron-jobs.md](35-cron-jobs.md)):
- `backup-database` nightly 2:00 AM
- `backup-files` nightly 2:30 AM
- `verify-backup` after backup
- Failure → alert (monitoring)

## Failure Behavior

- Backup failure → log to monitored channel + alert
- Restore failure → do NOT proceed; investigate; keep original backup
- Partial restore → restore from earlier known-good backup

## Recovery Time Objective / Point

- RPO: ≤ 24h (daily backups)
- RTO: ~2-4h (manual restore)

---

**Next**: [35-cron-jobs.md](35-cron-jobs.md) for scheduled jobs.