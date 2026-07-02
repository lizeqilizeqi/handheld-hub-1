# Handheld Hub launcher (ASCII-only for Windows PowerShell compatibility)
$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$RunDir = Join-Path $Root '.run'
$PidFile = Join-Path $RunDir 'launcher.pid'
$SiteUrl = 'http://localhost:8080/en/handhelds'
$AdminUrl = 'http://localhost:8080/admin/'

if (-not (Test-Path $RunDir)) {
    New-Item -ItemType Directory -Path $RunDir -Force | Out-Null
}

function Stop-OldLauncher {
    if (-not (Test-Path $PidFile)) { return }
    $oldPidText = (Get-Content $PidFile -Raw -ErrorAction SilentlyContinue)
    if ($null -eq $oldPidText) { return }
    $oldPidText = $oldPidText.Trim()
    if ($oldPidText -match '^\d+$') {
        $oldPid = [int]$oldPidText
        if ($oldPid -ne $PID) {
            $proc = Get-Process -Id $oldPid -ErrorAction SilentlyContinue
            if ($proc) {
                Write-Host "[INFO] Stopping previous launcher PID $oldPid"
                Stop-Process -Id $oldPid -Force -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 1
            }
        }
    }
    Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
}

function Add-DockerToPath {
    $dockerBin = 'C:\Program Files\Docker\Docker\resources\bin'
    if (Test-Path $dockerBin) {
        $env:Path = "$dockerBin;$env:Path"
    }
}

function Test-DockerEngine {
    docker info 2>$null | Out-Null
    return ($LASTEXITCODE -eq 0)
}

function Start-DockerDesktopAndWait {
    $exe = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'
    if (-not (Test-Path $exe)) {
        throw 'Docker Desktop not found. Please install Docker Desktop first.'
    }
    Write-Host '[INFO] Starting Docker Desktop...'
    Start-Process $exe | Out-Null
    $deadline = (Get-Date).AddMinutes(3)
    while ((Get-Date) -lt $deadline) {
        if (Test-DockerEngine) {
            Write-Host '[OK] Docker engine is running.'
            return
        }
        Write-Host '[WAIT] Docker engine starting...'
        Start-Sleep -Seconds 3
    }
    throw 'Docker start timeout. Open Docker Desktop manually and wait for Engine running.'
}

function Invoke-DockerCompose {
    param([string[]]$ComposeArgs)
    Push-Location $Root
    try {
        $prev = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $output = & docker compose @ComposeArgs 2>&1
        $code = $LASTEXITCODE
        $ErrorActionPreference = $prev
        if ($output) {
            $output | ForEach-Object { Write-Host $_ }
        }
        return $code
    } finally {
        Pop-Location
    }
}

function Stop-OldStack {
    Write-Host '[INFO] Stopping old containers (if any)...'
    $null = Invoke-DockerCompose -ComposeArgs @('down')
}

function Start-Stack {
    Write-Host '[INFO] Starting docker compose...'
    $code = Invoke-DockerCompose -ComposeArgs @('up', '-d')
    if ($code -ne 0) {
        throw 'docker compose up failed.'
    }
}

function Wait-ForSite {
    param(
        [string]$TargetUrl,
        [int]$MaxSeconds = 120
    )
    Write-Host "[INFO] Waiting for site: $TargetUrl"
    $deadline = (Get-Date).AddSeconds($MaxSeconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $resp = Invoke-WebRequest -Uri $TargetUrl -UseBasicParsing -TimeoutSec 3
            if ($resp.StatusCode -eq 200) {
                Write-Host '[OK] Site is ready.'
                return $true
            }
        } catch {
            # retry
        }
        Start-Sleep -Seconds 2
    }
    Write-Host '[WARN] Site slow to respond; opening browser anyway.'
    return $false
}

try {
    Write-Host ''
    Write-Host '========================================'
    Write-Host ' Handheld Hub - start'
    Write-Host '========================================'
    Write-Host ''

    Stop-OldLauncher
    Set-Content -Path $PidFile -Value $PID -Encoding ASCII -NoNewline

    Add-DockerToPath

    if (-not (Test-DockerEngine)) {
        Start-DockerDesktopAndWait
    } else {
        Write-Host '[OK] Docker engine already running.'
    }

    Stop-OldStack
    Start-Stack
    Wait-ForSite -TargetUrl $SiteUrl | Out-Null

    Write-Host "[INFO] Opening admin: $AdminUrl"
    Start-Process $AdminUrl
    Start-Sleep -Seconds 1
    Write-Host "[INFO] Opening public site: $SiteUrl"
    Start-Process $SiteUrl

    Write-Host ''
    Write-Host '[DONE] Project started.'
    Write-Host "  Site : $SiteUrl"
    Write-Host "  Admin: $AdminUrl"
    Write-Host ''
    Write-Host 'Note: closing this window does NOT stop containers.'
    Write-Host '      Run stop-project.bat to stop the project.'
    Write-Host ''

    exit 0
} catch {
    Write-Host ''
    Write-Host "[ERROR] $($_.Exception.Message)"
    Write-Host ''
    Remove-Item $PidFile -Force -ErrorAction SilentlyContinue
    exit 1
}
