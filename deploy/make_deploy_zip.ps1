<# ===========================================================================
 deploy/make_deploy_zip.ps1 — build the File Manager upload .zip (no SSH path)
 ---------------------------------------------------------------------------
 PRIMARY deploy artifact for cPanel File Manager users.

 HOST LAYOUT (this deployment — subdomain folder IS the webroot):
   <docroot>/ = testing.deepjyotimicrofinance.com/ (Apache serves this folder)
     index.php  .htaccess  portal/   <- web entry (flattened to zip root)
     app/  config/  database/  resources/  storage/  docs/  <- app code
     .env                                             <- created manually
   So the zip root holds index.php, .htaccess, portal/ BESIDE app/ etc.

 What it does:
   1. Stages app/ config/ database/ resources/ storage/ docs/ into a temp dir,
      excluding dev-only files + secrets via deploy/.deployignore
      (never .env, never *_test.php / phase*_*.php / rbac_* / *.zip).
   2. Flattens the webroot: copy public/index.php -> <stage>/index.php,
      public/.htaccess -> <stage>/.htaccess, public/portal -> <stage>/portal.
   3. Restores empty writable storage skeleton (logs, cache, temp,
      app/private/files).
   4. Writes deploy/fps_deploy_testing.zip = WHAT YOU UPLOAD VIA FILE MANAGER.

 Usage:
   powershell -ExecutionPolicy Bypass -File deploy\make_deploy_zip.ps1
#>

param(
    [string]$OutZip = (Join-Path $PSScriptRoot "fps_deploy_testing.zip")
)

# NOTE: the staging dir MUST NOT live inside the repo tree — it would be
# included in the robocopy of storage/ and recurse infinitely.
$ErrorActionPreference = "Stop"
$Root      = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$Stage     = Join-Path ([System.IO.Path]::GetTempPath()) "fps_deploy_stage"
$Ignore    = Join-Path $PSScriptRoot ".deployignore"
$Dirs      = @("app", "config", "database", "resources", "storage", "docs")

if (-not (Test-Path $Ignore)) { throw "Missing $Ignore" }

# ---- parse ignore (robocopy semantics: missing trailing / => file) ---------
$xd = @(); $xf = @()
foreach ($line in Get-Content $Ignore) {
    $t = $line.Trim()
    if ($t -eq '' -or $t -match '^#') { continue }
    $base = Split-Path -Leaf $t.TrimEnd('/')
    if ($base -eq '' -or $base -match '^\.+$') { continue }
    if ($t -match '/$') { $xd += $base } else { $xf += $base }
}

# ---- 1. clean + stage --------------------------------------------------------
if (Test-Path $Stage) { Remove-Item $Stage -Recurse -Force }
New-Item -ItemType Directory -Path $Stage -Force | Out-Null

foreach ($d in $Dirs) {
    $src = Join-Path $Root $d; $dst = Join-Path $Stage $d
    if (-not (Test-Path $src)) { Write-Host "  (skip missing dir: $d)"; continue }
    New-Item -ItemType Directory -Path $dst -Force | Out-Null
    robocopy $src $dst /E /XD $xd /XF $xf /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy failed for $d (exit $LASTEXITCODE)" }
}

# ---- 2. flatten webroot entries to the zip ROOT ------------------------------
Copy-Item -LiteralPath (Join-Path $Root "public\index.php")  -Destination (Join-Path $Stage "index.php")
Copy-Item -LiteralPath (Join-Path $Root "public\.htaccess")  -Destination (Join-Path $Stage ".htaccess")
Copy-Item -LiteralPath (Join-Path $Root "public\portal")     -Destination (Join-Path $Stage "portal") -Recurse
# one-time migrate+seed helper (DELETE from host after first successful run)
Copy-Item -LiteralPath (Join-Path $Root "run-deploy.php")     -Destination (Join-Path $Stage "run-deploy.php")
# TESTING-ONLY demo helper (creates SIH showcase data + demo logins; DELETE after use)
Copy-Item -LiteralPath (Join-Path $Root "run-demo.php")       -Destination (Join-Path $Stage "run-demo.php")

# ---- 3. empty writable storage skeleton --------------------------------------
foreach ($sub in @("logs", "cache", "temp")) {
    New-Item -ItemType Directory -Path (Join-Path (Join-Path $Stage "storage") $sub) -Force | Out-Null
}
New-Item -ItemType Directory -Path (Join-Path $Stage "storage\app\private\files") -Force | Out-Null

# ---- 4. extraction helper in the zip root ------------------------------------
$readme = @"
Farmer Procurement System - deploy zip (testing.deepjyotimicrofinance.com)

Your cPanel serves this subdomain folder DIRECTLY, so this zip is already laid
out to match the docroot. Extract it INTO:

  /home/<USER>/testing.deepjyotimicrofinance.com/

After extraction that folder holds everything it needs:
  index.php   .htaccess   portal/          <- web entry (leave as-is!)
  app/ config/ database/ resources/ storage/ docs/

NO manual moving is required. Three things to do after extraction:
  1. Permissions: files 644, dirs 755, storage/ 775 (right-click Permissions).
  2. Create the secret file .env in this same folder (from the .env.production
     template) and chmod it 600/640. NEVER leave it from the zip (it is not there).
  3. Run migrate + seed via browser (no cron needed initially):
       https://testing.deepjyotimicrofinance.com/run-deploy.php?confirm=YES
     Then DELETE run-deploy.php and run-deploy.done from this folder
     (File Manager). Re-upload run-deploy.php whenever new migrations appear.
  4. TESTING subdomain only - want demo showcase data + demo login accounts?
       https://testing.deepjyotimicrofinance.com/run-demo.php?confirm=YES
     (creates centres/slots/farmers/staff/bookings; demo passwords are PUBLIC -
     then DELETE run-demo.php + run-demo.done). NEVER run this on production.

PORTAL (staff/admin) after run-demo:
  - Login: username 'demo_super_admin'  password 'Admin@1234'
     (then change it: click your name top-right > Change Password)
  - Administration > Farmers > + Farmer : create farmers WITH a password so the
    farmer logs in directly in the farmer mobile app (mobile + password).
  - Administration > Staff > + Staff : create staff (they use forgot-password).

MOBILE APP: useitnow.apk is a separate artifact (D:\\sihproject\\useitnow.apk),
already pointed at https://testing.deepjyotimicrofinance.com. Share it with
farmers; they login with mobile + password.

The .htaccess already: serves portal/, routes API requests to index.php, forces
https, and blocks web access to app/ config/ database/ storage/ .env *.log etc.

Security note: on this host the whole app sits inside the public webroot. The
.htaccess deny-rules protect it. If the panel ever supports a custom docroot,
use the Option A/B layout in deploy/checklist.md instead.
"@
Set-Content -LiteralPath (Join-Path $Stage "README-DEPLOY.txt") -Value $readme -Encoding utf8

# ---- 5. write the zip (bsdtar: -a picks format from extension) ----------------
if (Test-Path $OutZip) { Remove-Item $OutZip -Force }
$tar = "$env:SystemRoot\System32\tar.exe"
Write-Host "Writing $OutZip ..."
& $tar -a -c -f $OutZip -C $Stage .
if ($LASTEXITCODE -ne 0) { throw "tar failed (exit $LASTEXITCODE)" }

# ---- 6. report ----------------------------------------------------------------
Write-Host ""
Write-Host "DONE. Upload this file via cPanel File Manager:"
Write-Host "  $OutZip"
Write-Host "Contents (first 40):"
& $tar -t -f $OutZip | Sort-Object | Select-Object -First 40
Write-Host "  ... (total entries: $((& $tar -t -f $OutZip | Measure-Object).Count))"

Remove-Item $Stage -Recurse -Force