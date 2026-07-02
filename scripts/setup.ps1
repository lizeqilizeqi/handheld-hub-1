# Handheld Hub — local setup helper (Windows PowerShell)
$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

Write-Host "=== Handheld Hub Setup ===" -ForegroundColor Cyan

function Test-Cmd($name) {
    $c = Get-Command $name -ErrorAction SilentlyContinue
    return [bool]$c
}

# Config
if (-not (Test-Path "$Root\config.local.php")) {
    Copy-Item "$Root\config.example.php" "$Root\config.local.php"
    Write-Host "[OK] Created config.local.php" -ForegroundColor Green
} else {
    Write-Host "[OK] config.local.php exists" -ForegroundColor Green
}

# Smoke test (Node)
if (Test-Cmd node) {
    Write-Host "`nRunning scrape smoke test (Node, no DB)..." -ForegroundColor Yellow
    node "$Root\scripts\smoke-scrape.mjs"
} else {
    Write-Host "[WARN] Node.js not found — skip smoke test" -ForegroundColor Yellow
}

Write-Host "`n--- Environment check ---" -ForegroundColor Cyan
$docker = Test-Cmd docker
$php = Test-Cmd php
Write-Host "Docker: $(if ($docker) { 'YES' } else { 'NO — install Docker Desktop' })"
Write-Host "PHP:    $(if ($php) { 'YES' } else { 'NO — use Docker or install PHP 8.3' })"

if ($docker) {
    Write-Host "`nStarting Docker Compose..." -ForegroundColor Yellow
    docker compose up -d --build
    Write-Host "Waiting for MySQL..." -ForegroundColor Yellow
    Start-Sleep -Seconds 15
    Write-Host "Running single-slug scrape test..." -ForegroundColor Yellow
    docker compose exec -T web php bin/scrape.php --slug=rg-rotate
    Write-Host "`nDone! Open:" -ForegroundColor Green
    Write-Host "  http://localhost:8080/admin/"
    Write-Host "  http://localhost:8080/en/handhelds"
} else {
    Write-Host "`nInstall Docker Desktop: https://www.docker.com/products/docker-desktop/" -ForegroundColor Yellow
    Write-Host "Then re-run: .\scripts\setup.ps1" -ForegroundColor Yellow
}
