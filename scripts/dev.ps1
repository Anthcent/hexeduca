<#
.SYNOPSIS
    Runs hexeduca locally on Windows: PostgreSQL in Docker, PHP server,
    queue worker, and Vite with hot reload.

.DESCRIPTION
    `composer run dev` assumes a Unix-like machine (pail needs pcntl) and the
    `php` on PATH. This script targets the local Windows setup instead:
    - PHP 8.3 from WinGet (override with the HEXEDUCA_PHP env var).
    - scripts/dev/php-dev.ini via PHP_INI_SCAN_DIR (10 MB uploads, OPcache).
    - The `pgsql` service from docker-compose.yml, reached on 127.0.0.1.
    - File cache/session and the database queue, because the local PHP has
      no Redis extension.
    Overrides are process environment variables, so .env stays untouched.

.PARAMETER Fresh
    Drop every table, migrate, and seed the demo data before starting.

.EXAMPLE
    composer run dev:windows
    composer run dev:windows -- -Fresh
#>
param(
    [switch] $Fresh
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$php = $env:HEXEDUCA_PHP
if (-not $php) {
    $php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
}
if (-not (Test-Path $php)) {
    throw "PHP 8.3 not found at '$php'. Install it with WinGet or set HEXEDUCA_PHP."
}
# Windows PowerShell drops embedded quotes when passing arguments to native
# commands, so a path with spaces (e.g. C:\Users\Jane Doe) would break the
# concurrently commands below. The 8.3 short path has no spaces.
$php = (New-Object -ComObject Scripting.FileSystemObject).GetFile($php).ShortPath

$env:PHP_INI_SCAN_DIR = Join-Path $PSScriptRoot 'dev'
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = if ($env:FORWARD_DB_PORT) { $env:FORWARD_DB_PORT } else { '5432' }
$env:CACHE_STORE = 'file'
$env:SESSION_DRIVER = 'file'
$env:QUEUE_CONNECTION = 'database'
$env:TENANCY_BASE_DOMAIN = 'localhost'
$env:APP_URL = 'http://localhost:8000'

Write-Host 'Starting PostgreSQL (docker compose service pgsql)...'
docker compose up -d pgsql
if ($LASTEXITCODE -ne 0) {
    throw 'docker compose failed. Is Docker Desktop running?'
}

$ready = $false
for ($i = 0; $i -lt 30; $i++) {
    docker compose exec -T pgsql pg_isready -q 2>$null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Start-Sleep -Seconds 1
}
if (-not $ready) {
    throw 'PostgreSQL did not become ready within 30 seconds.'
}

if ($Fresh) {
    & $php artisan migrate:fresh --seed --force
} else {
    & $php artisan migrate --force
}
if ($LASTEXITCODE -ne 0) {
    throw 'Database migration failed.'
}

# Module manifests own the permission catalog: renamed or new permissions
# must reach the database before any route checks them.
& $php artisan modules:sync
if ($LASTEXITCODE -ne 0) {
    throw 'Module sync failed.'
}

# A stale hot file makes every page point at a Vite server that is not running.
Remove-Item -ErrorAction SilentlyContinue (Join-Path $root 'public\hot')

Write-Host ''
Write-Host 'Landlord: http://admin.localhost:8000   School: http://demo.localhost:8000'
Write-Host ''

# --timeout=0: queue:listen otherwise kills its worker after 60 s of wall
# clock, which fires when the machine sleeps. Without -k, one process
# exiting doesn't take the others down.
npx concurrently -c '#93c5fd,#c4b5fd,#fdba74' --names 'server,queue,vite' `
    "$php artisan serve --host=localhost --port=8000" `
    "$php artisan queue:listen --tries=1 --timeout=0" `
    'npm run dev'
