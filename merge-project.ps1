param(
    [string]$TargetPath = "C:\Users\DELL\tassproject"
)

# TassProject - full project merge installer.
# Copies EVERY file built across all 9 phases into your existing
# project, in the correct final locations. Does NOT touch:
#   - .env files (backend, scraper, job-service) - your secrets/passwords
#   - vendor/, node_modules/, venv/ - your installed dependencies
#   - composer.json/lock, package.json/lock, angular.json, tsconfig*
#   - your MySQL/Redis Docker containers or data
# Safe to run on top of your existing tassproject folder.

$ErrorActionPreference = "Stop"
$src = Join-Path $PSScriptRoot "tassproject"

if (-not (Test-Path $TargetPath)) {
    Write-Host "Could not find $TargetPath - pass the right path with -TargetPath" -ForegroundColor Red
    exit 1
}

Write-Host "Merging full project into $TargetPath ..." -ForegroundColor Cyan
Write-Host ""

# ---------- BACKEND ----------
Write-Host "Backend:" -ForegroundColor Cyan
Copy-Item "$src\backend\app\Models\*" "$TargetPath\backend\app\Models\" -Force
Copy-Item "$src\backend\app\Http\Controllers\*.php" "$TargetPath\backend\app\Http\Controllers\" -Force
New-Item -ItemType Directory -Force -Path "$TargetPath\backend\app\Http\Controllers\Auth" | Out-Null
Copy-Item "$src\backend\app\Http\Controllers\Auth\*" "$TargetPath\backend\app\Http\Controllers\Auth\" -Force
New-Item -ItemType Directory -Force -Path "$TargetPath\backend\app\Http\Middleware" | Out-Null
Copy-Item "$src\backend\app\Http\Middleware\*" "$TargetPath\backend\app\Http\Middleware\" -Force
New-Item -ItemType Directory -Force -Path "$TargetPath\backend\app\Services" | Out-Null
Copy-Item "$src\backend\app\Services\*" "$TargetPath\backend\app\Services\" -Force
Copy-Item "$src\backend\database\migrations\*" "$TargetPath\backend\database\migrations\" -Force
Copy-Item "$src\backend\database\seeders\*" "$TargetPath\backend\database\seeders\" -Force
Copy-Item "$src\backend\routes\api.php" "$TargetPath\backend\routes\api.php" -Force
Copy-Item "$src\backend\bootstrap\app.php" "$TargetPath\backend\bootstrap\app.php" -Force
Copy-Item "$src\backend\config\cors.php" "$TargetPath\backend\config\cors.php" -Force
if (Test-Path "$src\backend\tests") {
    New-Item -ItemType Directory -Force -Path "$TargetPath\backend\tests\Feature" | Out-Null
    Copy-Item "$src\backend\tests\Feature\*.php" "$TargetPath\backend\tests\Feature\" -Force
    Write-Host "  Done: tests/Feature (php artisan test)" -ForegroundColor Green
}
Write-Host "  Done: models, controllers, middleware, services, migrations, seeders, routes" -ForegroundColor Green

# ---------- FRONTEND ----------
Write-Host "Frontend:" -ForegroundColor Cyan
Copy-Item "$src\frontend\src\app" "$TargetPath\frontend\src\" -Recurse -Force
Copy-Item "$src\frontend\src\environments" "$TargetPath\frontend\src\" -Recurse -Force
Copy-Item "$src\frontend\src\brand-tokens.css" "$TargetPath\frontend\src\brand-tokens.css" -Force
Copy-Item "$src\frontend\src\styles.css" "$TargetPath\frontend\src\styles.css" -Force
Copy-Item "$src\frontend\src\index.html" "$TargetPath\frontend\src\index.html" -Force
Copy-Item "$src\frontend\public\logo" "$TargetPath\frontend\public\" -Recurse -Force
Write-Host "  Done: all app code, environments, brand tokens, logo, styles.css, index.html" -ForegroundColor Green

# ---------- SCRAPER ----------
Write-Host "Scraper:" -ForegroundColor Cyan
Copy-Item "$src\scraper\*.py" "$TargetPath\scraper\" -Force
Copy-Item "$src\scraper\requirements.txt" "$TargetPath\scraper\requirements.txt" -Force
New-Item -ItemType Directory -Force -Path "$TargetPath\scraper\ml" | Out-Null
Copy-Item "$src\scraper\ml\*" "$TargetPath\scraper\ml\" -Force
if (-not (Test-Path "$TargetPath\scraper\.env")) {
    Copy-Item "$src\scraper\.env.example" "$TargetPath\scraper\.env.example" -Force
    Write-Host "  Done: all .py files, ml/ package, requirements.txt" -ForegroundColor Green
} else {
    Write-Host "  Done: all .py files, ml/ package, requirements.txt (kept your existing .env)" -ForegroundColor Green
}

# ---------- JOB SERVICE ----------
Write-Host "Job service:" -ForegroundColor Cyan
if (Test-Path "$TargetPath\job-service") {
    Copy-Item "$src\job-service\src" "$TargetPath\job-service\" -Recurse -Force
    Copy-Item "$src\job-service\package.json" "$TargetPath\job-service\package.json" -Force
    Write-Host "  Done: src/ and package.json (kept your existing .env and node_modules)" -ForegroundColor Green
} else {
    New-Item -ItemType Directory -Force -Path "$TargetPath\job-service" | Out-Null
    Copy-Item "$src\job-service\*" "$TargetPath\job-service\" -Recurse -Force
    Write-Host "  Created job-service folder fresh - remember to run 'npm install' in it" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Merge complete." -ForegroundColor Cyan
Write-Host "Next: restart your 5 windows (backend, frontend, scraper, job-service API, job-service workers)." -ForegroundColor Cyan
Write-Host "If this is a fresh scraper/job-service setup, remember: pip install -r requirements.txt / npm install" -ForegroundColor Cyan
