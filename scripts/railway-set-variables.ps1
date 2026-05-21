#Requires -Version 5.1
<#
.SYNOPSIS
  Push Symfony environment variables to Railway for the linked service.

.PREREQUISITES
  1. railway login
  2. railway link   (select your project + the PHP/app service)
  3. Optional: rename MySQL service to "MySQL" or pass -DatabaseServiceName

.USAGE
  .\scripts\railway-set-variables.ps1
  .\scripts\railway-set-variables.ps1 -DatabaseServiceName "MySQL" -SkipDeploys
#>
param(
    [string]$DatabaseServiceName = "MySQL",
    [string]$EnvFile = ".env.railway",
    [string]$LocalEnvFile = ".env",
    [switch]$SkipDeploys,
    [switch]$UseLocalDatabaseUrl
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $ProjectRoot

function Test-RailwayCli {
    $null = Get-Command railway -ErrorAction Stop
}

function Get-EnvMap {
    param([string]$Path)
    $map = @{}
    if (-not (Test-Path $Path)) {
        return $map
    }
    Get-Content $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq "" -or $line.StartsWith("#")) { return }
        $idx = $line.IndexOf("=")
        if ($idx -lt 1) { return }
        $key = $line.Substring(0, $idx).Trim()
        $value = $line.Substring($idx + 1).Trim()
        if ($value.StartsWith('"') -and $value.EndsWith('"')) {
            $value = $value.Substring(1, $value.Length - 2)
        }
        $map[$key] = $value
    }
    return $map
}

function New-RandomHex {
    param([int]$Bytes = 32)
    $buffer = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return ([BitConverter]::ToString($buffer) -replace "-", "").ToLower()
}

Test-RailwayCli

$status = railway status 2>&1 | Out-String
if ($status -match "No linked project") {
    Write-Error "No Railway project linked. Run: railway login`nThen: railway link"
}

$vars = Get-EnvMap -Path $EnvFile
if ($vars.Count -eq 0) {
    Write-Error "Missing or empty $EnvFile"
}

$local = Get-EnvMap -Path $LocalEnvFile

# Merge local .env (secrets you already use locally), except Railway-specific keys
$preserveFromTemplate = @(
    "APP_ENV", "APP_DEBUG", "DEFAULT_URI", "DATABASE_URL", "CORS_ALLOW_ORIGIN"
)

foreach ($key in $local.Keys) {
    $value = $local[$key]
    if ([string]::IsNullOrWhiteSpace($value)) { continue }
    if ($preserveFromTemplate -contains $key) { continue }
    if ($key -eq "DATABASE_URL" -and -not $UseLocalDatabaseUrl) { continue }
    $vars[$key] = $value
}

# Railway template values
$vars["APP_ENV"] = "prod"
$vars["APP_DEBUG"] = "0"
$vars["DEFAULT_URI"] = "https://`${{RAILWAY_PUBLIC_DOMAIN}}"
$vars["DATABASE_URL"] = "`${{$DatabaseServiceName.MYSQL_URL}}"

if ([string]::IsNullOrWhiteSpace($vars["APP_SECRET"]) -or $vars["APP_SECRET"] -match "REPLACE_") {
    $vars["APP_SECRET"] = New-RandomHex -Bytes 32
    Write-Host "Generated new APP_SECRET for Railway."
}

if ([string]::IsNullOrWhiteSpace($vars["JWT_PASSPHRASE"]) -or $vars["JWT_PASSPHRASE"] -match "REPLACE_") {
    $vars["JWT_PASSPHRASE"] = New-RandomHex -Bytes 16
    Write-Host "Generated new JWT_PASSPHRASE for Railway."
}

if ([string]::IsNullOrWhiteSpace($vars["MESSENGER_TRANSPORT_DSN"])) {
    $vars["MESSENGER_TRANSPORT_DSN"] = "doctrine://default?auto_setup=0"
}

if ([string]::IsNullOrWhiteSpace($vars["MAILER_DSN"])) {
    $vars["MAILER_DSN"] = "null://null"
}

Write-Host "Setting $($vars.Count) variables on Railway..."
foreach ($key in ($vars.Keys | Sort-Object)) {
    $value = $vars[$key]
    $pair = "${key}=${value}"
    Write-Host "  $key"
    if ($SkipDeploys) {
        railway variable set $pair --skip-deploys 2>&1 | Out-Null
    } else {
        railway variable set $pair 2>&1 | Out-Null
    }
}

Write-Host ""
Write-Host "Done. Verify with: railway variable list"
Write-Host "Ensure MySQL service is named '$DatabaseServiceName' or re-run with -DatabaseServiceName."
Write-Host "Enable a public domain on the app service so RAILWAY_PUBLIC_DOMAIN is set."
