#!/usr/bin/env bash
# ============================================================================
# deploy/sync.sh — rsync/tar deploy for SSH users
# NOTE: this host has NO SSH — use deploy/fps_deploy_testing.zip + File Manager.
# This script is the SSH equivalent, matching the SAME virtual-host layout:
#
#   <docroot> = the cPanel subdomain folder (e.g. testing.deepjyotimicrofinance.com/)
#     index.php  .htaccess  portal/           <- web entry (at the folder root)
#     app/ config/ database/ resources/ storage/ docs/  <- app code (same folder)
#     .env                                       <- created manually, NEVER synced
#
# EXCLUDE list source: deploy/.deployignore (basename patterns work with rsync).
# ============================================================================
set -euo pipefail

SSH_TARGET="user@host.example.com"                  # EDIT
DOCROOT="/home/user/testing.deepjyotimicrofinance.com"   # EDIT (subdomain folder)
PHP_BIN="php"                                       # host php CLI (try /usr/local/bin/php)

echo "==> staging + syncing the deploy tree (respects .deployignore + .gitignore)"
rsync -azh \
  --exclude-from=.gitignore \
  --exclude-from=deploy/.deployignore \
  app/ config/ database/ resources/ storage/ docs/ \
  "${SSH_TARGET}:${DOCROOT}/"

echo "==> flattening the webroot (index.php, .htaccess, portal/ to the folder root)"
rsync -azh public/ "${SSH_TARGET}:${DOCROOT}/"

echo "==> permissions (files 644, dirs 755, storage writable, .env 600 if present)"
ssh "${SSH_TARGET}" bash -s <<EOF
find "${DOCROOT}" -type f -exec chmod 644 {} \;
find "${DOCROOT}" -type d -exec chmod 755 {} \;
mkdir -p "${DOCROOT}/storage/logs"  "${DOCROOT}/storage/cache"  "${DOCROOT}/storage/temp"  "${DOCROOT}/storage/app/private/files"
chmod -R 775 "${DOCROOT}/storage"
[ -f "${DOCROOT}/.env" ] && chmod 600 "${DOCROOT}/.env"
EOF

echo "==> migrate + non-demo seed (idempotent; NEVER --demo/--reset on prod)"
ssh "${SSH_TARGET}" "cd '${DOCROOT}' && ${PHP_BIN} app/console/migrate.php run && ${PHP_BIN} app/console/migrate.php status"
ssh "${SSH_TARGET}" "cd '${DOCROOT}' && ${PHP_BIN} app/console/seed.php"

echo "==> smoke"
HEALTH_URL="https://testing.deepjyotimicrofinance.com/health"
ssh "${SSH_TARGET}" "curl -sS -o /dev/null -w '%{http_code} health\\n' ${HEALTH_URL}"

# ============================================================================
# ZIP route (the one actually used for THIS host, on the dev machine):
#   powershell -ExecutionPolicy Bypass -File deploy\make_deploy_zip.ps1
# then upload deploy/fps_deploy_testing.zip via File Manager and extract it IN
# the subdomain folder (see deploy/checklist.md §5 + README-DEPLOY.txt).
# ============================================================================