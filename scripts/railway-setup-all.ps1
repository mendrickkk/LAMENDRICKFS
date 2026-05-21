#Requires -Version 5.1
<#
  One-shot Railway setup: login (if needed), link project, set all Symfony variables.
  Run from an interactive terminal (Cursor terminal or PowerShell):

    cd "c:\IT 2ND SEM 3RD YEAR\ITS 306 - WEB DEV\FINALSS"
    .\scripts\railway-setup-all.ps1
#>
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $ProjectRoot

Write-Host "=== Railway setup for La Mendrick FS ===" -ForegroundColor Cyan
Write-Host ""

if (-not (Get-Command railway -ErrorAction SilentlyContinue)) {
    Write-Error "Railway CLI not found. Install: scoop install railway  OR  npm i -g @railway/cli"
}

$whoami = railway whoami 2>&1 | Out-String
if ($whoami -match "Unauthorized|login again") {
    Write-Host "Step 1: Log in to Railway (browser will open)..." -ForegroundColor Yellow
    railway login
}

Write-Host ""
$status = railway status 2>&1 | Out-String
if ($status -match "No linked project") {
    Write-Host "Step 2: Link this folder to your Railway project + APP service..." -ForegroundColor Yellow
    Write-Host "  (Select the PHP/web service, NOT the MySQL service)" -ForegroundColor Gray
    railway link
}

Write-Host ""
Write-Host "Step 3: Setting environment variables..." -ForegroundColor Yellow
& "$ProjectRoot\scripts\railway-set-variables.ps1" -SkipDeploys

Write-Host ""
Write-Host "Step 4: Verify variables" -ForegroundColor Yellow
railway variable list

Write-Host ""
Write-Host "=== Done ===" -ForegroundColor Green
Write-Host "Next in Railway dashboard:"
Write-Host "  1. MySQL service linked to app (DATABASE_URL reference)"
Write-Host "  2. App service: Generate Domain"
Write-Host "  3. Redeploy the app service"
Write-Host ""
Read-Host "Press Enter to close"
