# Handheld Hub - export local migration bundle (DB + config + images)
# Usage:
#   .\deploy\export-local-migration.ps1
#   .\deploy\export-local-migration.ps1 -BaseUrl "http://oldman.dpdns.org"
param(
    [string]$BaseUrl = "http://oldman.dpdns.org",
    [string]$ProjectRoot = (Split-Path -Parent $PSScriptRoot)
)

$ErrorActionPreference = "Stop"
$ProjectRoot = (Resolve-Path $ProjectRoot).Path
$ComposeFile = Join-Path $ProjectRoot "docker-compose.yml"
$OutDir = Join-Path $ProjectRoot "deploy\out\hh-migration-bundle"
$TarPath = Join-Path $ProjectRoot "deploy\out\hh-migration-bundle.tar.gz"

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }

if (-not (Test-Path $ComposeFile)) {
    throw "docker-compose.yml not found: $ComposeFile"
}

Write-Step "Checking Docker containers"
$dbStatus = docker compose -f $ComposeFile ps db --status running -q 2>$null
if (-not $dbStatus) {
    throw "Local db container is not running. Run: docker compose up -d"
}

$BaseUrl = $BaseUrl.TrimEnd('/')
$redirectUri = "$BaseUrl/admin/blogger_oauth.php"

Write-Step "Preparing output directory"
if (Test-Path $OutDir) { Remove-Item $OutDir -Recurse -Force }
New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
New-Item -ItemType Directory -Path (Split-Path $TarPath) -Force | Out-Null

Write-Step "Exporting MySQL database"
$sqlPath = Join-Path $OutDir "database.sql"
$dumpInContainer = "mysqldump -u handheld -phandheld --single-transaction --routines --triggers --add-drop-table --no-tablespaces handheld_hub"
$prevEap = $ErrorActionPreference
$ErrorActionPreference = "Continue"
docker compose -f $ComposeFile exec -T db sh -c "$dumpInContainer > /tmp/hh-dump.sql" 2>$null
if ($LASTEXITCODE -ne 0) {
    throw "mysqldump inside container failed"
}
docker compose -f $ComposeFile cp "db:/tmp/hh-dump.sql" $sqlPath 2>$null
docker compose -f $ComposeFile exec -T db rm -f /tmp/hh-dump.sql 2>$null
$ErrorActionPreference = $prevEap
if (-not (Test-Path $sqlPath) -or (Get-Item $sqlPath).Length -lt 1000) {
    throw "database.sql export failed or file too small"
}

Write-Step "Writing config.local.php (base_url -> $BaseUrl)"
$localCfg = Join-Path $ProjectRoot "config.local.php"
if (-not (Test-Path $localCfg)) {
    throw "config.local.php missing"
}
$content = Get-Content $localCfg -Raw
$content = $content -replace "mysql:host=127\.0\.0\.1", "mysql:host=db"
$content = $content -replace "'base_url'\s*=>\s*'[^']*'", "'base_url' => '$BaseUrl'"
$content = $content -replace "'redirect_uri'\s*=>\s*'[^']*'", "'redirect_uri' => '$redirectUri'"
Set-Content -Path (Join-Path $OutDir "config.local.php") -Value $content -NoNewline

Write-Step "Writing config.secrets.php"
$secretsCfg = Join-Path $ProjectRoot "config.secrets.php"
if (-not (Test-Path $secretsCfg)) {
    throw "config.secrets.php missing"
}
$secrets = Get-Content $secretsCfg -Raw
$secrets = $secrets -replace "http://localhost:8080/admin/blogger_oauth\.php", $redirectUri
$secrets = $secrets -replace "http://127\.0\.0\.1:8080/admin/blogger_oauth\.php", $redirectUri
$secrets = $secrets -replace "'redirect_uri'\s*=>\s*'[^']*'", "'redirect_uri' => '$redirectUri'"
Set-Content -Path (Join-Path $OutDir "config.secrets.php") -Value $secrets -NoNewline

Write-Step "Packing storage/handhelds images"
$storageSrc = Join-Path $ProjectRoot "storage\handhelds"
$storageTar = Join-Path $OutDir "storage-handhelds.tar.gz"
if (Test-Path $storageSrc) {
    tar -czf $storageTar -C $storageSrc .
} else {
    Write-Host "    (no storage/handhelds, skipped)" -ForegroundColor Yellow
}

$manifest = @"
Handheld Hub migration bundle
created_utc=$(Get-Date -Format "yyyy-MM-ddTHH:mm:ssZ")
base_url=$BaseUrl
redirect_uri=$redirectUri
handhelds_dir=storage-handhelds.tar.gz
database=database.sql
"@
Set-Content -Path (Join-Path $OutDir "MANIFEST.txt") -Value $manifest -Encoding UTF8

Write-Step "Creating hh-migration-bundle.tar.gz"
if (Test-Path $TarPath) { Remove-Item $TarPath -Force }
tar -czf $TarPath -C (Split-Path $OutDir) (Split-Path $OutDir -Leaf)

$sizeMb = [math]::Round((Get-Item $TarPath).Length / 1MB, 2)
Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " Migration bundle ready" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host "  File: $TarPath"
Write-Host "  Size: ${sizeMb} MB"
Write-Host ""
Write-Host " Next steps:"
Write-Host "  1. GCP browser SSH -> Upload file -> select the tar.gz above"
Write-Host "  2. GCP VM -> Edit -> Startup script -> paste deploy/gcp-startup-script.sh -> Save"
Write-Host "  3. Reset VM, wait 2-3 minutes"
Write-Host "  4. Add OAuth Redirect URI in Google Cloud:"
Write-Host "     $redirectUri"
Write-Host "========================================" -ForegroundColor Green
