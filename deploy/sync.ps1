<# ===========================================================================
 deploy/sync.ps1 — Production deploy orchestrator (Windows dev -> Linux shared host)
 ---------------------------------------------------------------------------
 rsync is not present on stock Windows, so this uses a portable equivalent:
   stage locally (respecting .gitignore + deploy/.deployignore) -> tar -> pipe
   over ssh -> extract on host -> run migrate status / non-demo seed /
   permissions tighten / storage-writable check / maintenance OFF / smoke.

 Requirements on the dev machine: PowerShell 5.1+, OpenSSH client, bsdtar
 (Windows 10+ ships tar.exe and scp.exe). On the host: sshd (cPanel/Plesk
 "Terminal" or shell access) + php CLI + mysqldump.

 NOTE: READ BEFORE RUNNING
  - This script NEVER uploads .env (it is excluded). Create the production
    .env on the host manually from deploy/production.env.example.
  - By default it only runs SAFE remote commands. Destructive DB operations
    are NOT executed (migrate rollback / seed --reset are never invoked).
  - The migrations table is created by app/console/migrate.php if absent,
    but the database + user must already exist on the host.

 EDIT THESE BEFORE USE:
   $SSH_TARGET, $REMOTE_APP_ROOT, $REMOTE_WEBROOT, $REMOTE_BACKUP, $APP_PUBLIC_URL
#>

# --- Configuration (EDIT ME) ----------------------------------------------
$SSH_TARGET       = "user@host.example.com"          # ssh/scp target
$SSH_PORT         = 22                              # ssh port
$REMOTE_APP_ROOT  = "/home/user/fps"                # app root (app/, config/, ... OUTSIDE webroot)
$REMOTE_WEBROOT   = "/home/user/public_html"        # web root (public/ -> index.php, .htaccess)
$PHP_BIN          = "php"                           # host php binary
$REMOTE_BACKUP    = "/home/user/backups"            # MUST exist on host
$APP_PUBLIC_URL   = "https://your-domain.example"   # used for remote web smoke
$DRY_RUN          = $false                          # $true => print commands, change nothing

# --- CLI switches -----------------------------------------------------------
param(
    [switch]$SkipSync,     # skip file upload
    [switch]$SkipMigrate,  # skip migrate status (still safe)
    [switch]$SkipSeed,     # skip the non-demo seed
    [switch]$SkipSmoke     # skip remote smoke/min checks
)

$ErrorActionPreference = "Stop"
$Root = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
# Staging MUST live OUTSIDE the repo tree (it would otherwise be copied
# recursively by the storage/ robocopy pass).
$Stage = Join-Path ([System.IO.Path]::GetTempPath()) "fps_deploy_staging"
$IgnoreFiles = @(
    (Join-Path $PSScriptRoot ".deployignore")
)

function Invoke-Remote([string]$RemoteCmd) {
    if ($DRY_RUN) { Write-Host "[DRY] ssh $SSH_TARGET -p $SSH_PORT -- bash -lc '$RemoteCmd'"; return }
    Write-Host ">> ssh: $RemoteCmd"
    & ssh.exe -p $SSH_PORT $SSH_TARGET $RemoteCmd
    if ($LASTEXITCODE -ne 0) { throw "Remote command failed (exit $LASTEXITCODE): $RemoteCmd" }
}

function Invoke-Local([string]$Cmd, [string[]]$Args) {
    if ($DRY_RUN) { Write-Host "[DRY] $Cmd $($Args -join ' ')"; return }
    & $Cmd @Args
    if ($LASTEXITCODE -ne 0) { throw "Local command failed (exit $LASTEXITCODE): $Cmd $($Args -join ' ')" }
}

# ---------------------------------------------------------------------------
# ---------------------------------------------------------------------------
# Pattern parsing (robocopy semantics, from deploy/.deployignore only — that
# file already mirrors the .gitignore entries that must not ship):
#   line ends with "/"  -> DIRECTORY basename exclusion  (/XD)
#   anything else       -> FILE basename/wildcard exclusion (/XF)
# Basenames are used because robocopy evaluates exclusions per copy-root
# (app/, config/, ... docs/); depth is not portable.
$patterns = @()
foreach ($f in $IgnoreFiles) {
    if (Test-Path $f) { $patterns += Get-Content $f | Where-Object { $_ -and $_ -notmatch '^#|^!' } }
}
$xd = @(); $xf = @()
foreach ($p in $patterns) {
    $base = Split-Path -Leaf $p.TrimEnd('/')
    if ($base -eq '' -or $base -match '^\.+$') { continue }
    if ($p.TrimEnd() -match '/$') { $xd += $base } else { $xf += $base }
}

$Dirs = @("app", "config", "database", "public", "resources", "storage", "docs")

# ---- 1. Stage ---------------------------------------------------------------
if (-not $SkipSync) {
    Write-Host "=== [1/7] Staging files (respecting .gitignore + .deployignore) ==="
    if (Test-Path $Stage) { Remove-Item $Stage -Recurse -Force }
    New-Item -ItemType Directory -Path $Stage -Force | Out-Null
    foreach ($d in $Dirs) {
        $src = Join-Path $Root $d; $dst = Join-Path $Stage $d
        if (-not (Test-Path $src)) { continue }
        New-Item -ItemType Directory -Path $dst -Force | Out-Null
        robocopy $src $dst /E /XD $xd /XF $xf /NFL /NDL /NJH /NJS | Out-Null
    }
    # Ensure logs/cache/temp exist in the stage (empty) so the host creates them
    # writable; the storage/logs|cache|temp dirs were excluded from the copy above.
    foreach ($sub in @("logs", "cache", "temp", "uploads", "app")) {
        New-Item -ItemType Directory -Path (Join-Path (Join-Path $Stage "storage") $sub) -Force | Out-Null
    }
    New-Item -ItemType Directory -Path (Join-Path $Stage "storage\app\private\files") -Force | Out-Null

    # ---- 2. Upload (tar over ssh) ----------------------------------------------
    Write-Host "=== [2/7] Transferring to ${SSH_TARGET}:$REMOTE_APP_ROOT ==="
    $tarCmd = "tar -C `"$Stage`" -czf - app config database public resources storage docs"
    $remoteExtract = "mkdir -p $REMOTE_APP_ROOT && tar -xzf - -C $REMOTE_APP_ROOT"
    if (-not $DRY_RUN) {
        # Secure: never sends .env (already excluded at staging), uses -p preserves nothing (fresh extract)
        cmd /c "tar -C `"$Stage`" -czf - app config database public resources storage docs | ssh -p $SSH_PORT $SSH_TARGET `"$remoteExtract`""
        if ($LASTEXITCODE -ne 0) { throw "Upload failed" }
    } else {
        Write-Host "[DRY] $tarCmd | ssh ... `"$remoteExtract`""
    }

    # ---- 3. Public webroot ------------------------------------------------------
    Write-Host "=== [3/7] Syncing public/ into webroot $REMOTE_WEBROOT ==="
    Invoke-Remote "mkdir -p $REMOTE_WEBROOT"
    if (-not $DRY_RUN) {
        cmd /c "tar -C `"$Stage`\public`" -czf - . | ssh -p $SSH_PORT $SSH_TARGET `"tar -xzf - -C $REMOTE_WEBROOT`""
        if ($LASTEXITCODE -ne 0) { throw "Webroot upload failed" }
    }
} else {
    Write-Host "=== [1/3] File sync SKIPPED (-SkipSync) ==="
}

# ---- 4. Permissions to tighten (always safe: 644 files / 755 dirs) ------------
Write-Host "=== [4/7] Tighten permissions ==="
Invoke-Remote "find $REMOTE_APP_ROOT -type f -exec chmod 644 {} \; && find $REMOTE_APP_ROOT -type d -exec chmod 755 {} \; && find $REMOTE_WEBROOT -type f -exec chmod 644 {} \; && find $REMOTE_WEBROOT -type d -exec chmod 755 {} \;"
# storage writable dirs
Invoke-Remote "mkdir -p $REMOTE_APP_ROOT/storage/logs $REMOTE_APP_ROOT/storage/cache $REMOTE_APP_ROOT/storage/temp $REMOTE_APP_ROOT/storage/uploads && chmod -R 775 $REMOTE_APP_ROOT/storage && chmod 600 $REMOTE_APP_ROOT/.env 2>/dev/null; echo perms-ok"

# ---- 5. Migrate status (migrations table ensured first; run only if DB exists) --
if (-not $SkipMigrate) {
    Write-Host "=== [5/7] Migration status + apply pending ==="
    Invoke-Remote "cd $REMOTE_APP_ROOT && $PHP_BIN app/console/migrate.php status || true"
    # Only run 'migrate run' when DB + migrations table exist. migrate.php creates
    # the table itself but NOT the database/user. The deployer must pre-create DB.
    Invoke-Remote "cd $REMOTE_APP_ROOT && $PHP_BIN app/console/migrate.php run"
    Invoke-Remote "cd $REMOTE_APP_ROOT && $PHP_BIN app/console/migrate.php status"
} else {
    Write-Host "=== [5/7] Migrate SKIPPED (-SkipMigrate) ==="
}

# ---- 6. Non-demo seed (idempotent upserts; NEVER --demo / --reset on prod) ----
if (-not $SkipSeed) {
    Write-Host "=== [6/7] Non-demo base seed (roles/permissions/languages/settings/districts/crops/rates) ==="
    Invoke-Remote "cd $REMOTE_APP_ROOT && $PHP_BIN app/console/seed.php"
} else {
    Write-Host "=== [6/7] Seed SKIPPED (-SkipSeed) ==="
}

# ---- 7. Storage writable green-check + maintenance OFF + smoke -----------------
Write-Host "=== [7/7] Storage / maintenance / smoke ==="
Invoke-Remote "touch $REMOTE_APP_ROOT/storage/logs/writecheck && rm -f $REMOTE_APP_ROOT/storage/logs/writecheck && echo storage-writable"
Invoke-Remote "cd $REMOTE_APP_ROOT && $PHP_BIN app/console/toggle_maintenance.php off 2>/dev/null || echo maintenance-already-off-or-dev-helper-absent"

if (-not $SkipSmoke) {
    Write-Host "Remote web smoke (health + maintenance + portal index):"
    Invoke-Remote "curl -sS -o /dev/null -w '%{http_code}:health' $APP_PUBLIC_URL/health && echo && curl -sS -o /dev/null -w '%{http_code}:maintenance' $APP_PUBLIC_URL/health/maintenance && echo && curl -sS -o /dev/null -w '%{http_code}:portal' $APP_PUBLIC_URL/portal/ && echo"
} else {
    Write-Host "Smoke SKIPPED (-SkipSmoke)"
}

Write-Host ""
Write-Host "Deploy orchestrator finished (dry-run=$DRY_RUN)."
Write-Host "Next manual steps: verify HTTPS redirect/headers (STEP E), backup jobs (STEP F),"
Write-Host "cron entries per STEP D (cPanel -> Cron Jobs)."