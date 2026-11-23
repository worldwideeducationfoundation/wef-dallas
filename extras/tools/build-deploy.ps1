# Builds the two files a Hostinger deploy needs, into extras\deploy\:
#
#   wef-website-YYYYMMDD-HHMM.zip   the contents of website\, ready to extract
#                                   straight into public_html
#   wef-database-YYYYMMDD-HHMM.sql  the local database, to import with
#                                   phpMyAdmin into the Hostinger database
#
# Both hold secrets (config.php, admin password hashes, every form
# submission). extras\deploy\ is gitignored; do not commit or share them.
#
# Usage, from the repository root, with XAMPP's MySQL running:
#   powershell -ExecutionPolicy Bypass -File extras\tools\build-deploy.ps1
#
# See extras\DEPLOY-HOSTINGER.md for what to do with the two files.

param(
    [string]$MysqlBin = 'C:\xampp\mysql\bin',
    [string]$Database = 'wef_admin',
    [string]$User = 'root'
)

$ErrorActionPreference = 'Stop'
$repo = Resolve-Path (Join-Path $PSScriptRoot '..\..')
$site = Join-Path $repo 'website'
$out = Join-Path $repo 'extras\deploy'
$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
New-Item -ItemType Directory -Force $out | Out-Null

# The live block of config.php must be filled in before this is uploaded.
$config = Get-Content (Join-Path $site 'config\config.php') -Raw
if ($config -match "CHANGE-ME|u000000000_") {
    Write-Warning "website\config\config.php still has the placeholder LIVE database settings. Fill them in (or edit config\config.php in hPanel's File Manager after uploading)."
}

# --- Website zip --------------------------------------------------------
# tar.exe ships with Windows 10+ and writes forward-slash paths, which Linux
# unzips correctly (PowerShell 5's Compress-Archive writes backslashes, which
# Hostinger would extract as filenames containing '\'). Logs and local
# backups stay behind; the empty folders are kept through their .gitkeep.
#
# The pin verifier (verify-centers.php + js/verifier.js) is a local-only
# tool: it has no sign-in and writes the map file and storage/ for anyone
# who opens it, so it never goes live. The map now reads admin -> Map
# Centers, so the live site does not need it.
$zip = Join-Path $out "wef-website-$stamp.zip"
& tar.exe -a -c -f $zip -C $site `
    --exclude '*.log' `
    --exclude '*.bak' `
    --exclude 'storage/backups/*.gz' `
    --exclude 'storage/backups/*.sql' `
    --exclude 'storage/backups/*.geojson' `
    --exclude 'storage/verification_audit.json' `
    --exclude 'public/verify-centers.php' `
    --exclude 'public/js/verifier.js' `
    .htaccess public includes config storage
if ($LASTEXITCODE -ne 0) { throw "tar failed ($LASTEXITCODE)" }

# Check the zip: the files the site cannot run without are in it, and the
# local-only ones above are not.
$listing = & tar.exe -t -f $zip
foreach ($required in @('.htaccess', 'public/.htaccess', 'public/index.php', 'public/admin/login.php', 'config/config.php', 'includes/db.php', 'storage/.htaccess')) {
    if (-not ($listing -contains $required)) { throw "The zip is missing $required" }
}
$stray = $listing | Where-Object { $_ -match '(verify-centers\.php|verifier\.js|verification_audit\.json|\.log|\.bak)$' }
if ($stray) { throw "The zip holds local-only files: $($stray -join ', ')" }

# --- Database export ----------------------------------------------------
# No CREATE DATABASE / USE: Hostinger's database has its own u123_ name, so
# the dump is imported into whichever database is selected in phpMyAdmin.
$sql = Join-Path $out "wef-database-$stamp.sql"
& (Join-Path $MysqlBin 'mysqldump.exe') --user=$User --single-transaction --routines --triggers `
    --default-character-set=utf8mb4 --hex-blob --add-drop-table --no-tablespaces `
    --result-file=$sql $Database
if ($LASTEXITCODE -ne 0) { throw "mysqldump failed ($LASTEXITCODE)" }

"Wrote:"
Get-Item $zip, $sql | ForEach-Object { "  {0}  ({1:N1} MB)" -f $_.FullName, ($_.Length / 1MB) }
