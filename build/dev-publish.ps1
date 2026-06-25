# VexyThemes Dev Workflow
# Usage:
#   .\build\dev-publish.ps1 -Version "3.1.0" -Changelog "Fixed bugs"
#   Publishes current theme to update server as a new version

param(
    [Parameter(Mandatory=$true)]
    [string]$Version,
    
    [Parameter(Mandatory=$false)]
    [string]$Changelog = "",
    
    [Parameter(Mandatory=$false)]
    [string]$Session = ""
)

$ErrorActionPreference = "Stop"
$apiUrl = "https://vt-panel-api.vercel.app"
$themePath = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full"
$zipDir = "C:\Users\akshi\Desktop\HYPER-KI-MKC\build\releases"

Write-Host "=== VexyThemes Dev Publish ===" -ForegroundColor Cyan
Write-Host "Version: $Version"
Write-Host ""

# Step 1: Update version file
Write-Host "[1/5] Updating version file..." -ForegroundColor Yellow
$versionData = @{
    version = $Version
    released = (Get-Date -Format "yyyy-MM-dd")
    channel = "stable"
    min_php = "8.2"
    changelog = $Changelog
    download_url = ""
    checksum = ""
    requirements = @{
        pterodactyl = ">=1.11.0"
        php = ">=8.2"
    }
} | ConvertTo-Json -Depth 5

$versionFile = Join-Path $themePath "config\vexythemes-version.json"
[System.IO.File]::WriteAllText($versionFile, $versionData)
Write-Host "  Version file updated" -ForegroundColor Green

# Step 2: Create zip
Write-Host "[2/5] Creating theme zip..." -ForegroundColor Yellow
if (!(Test-Path $zipDir)) { New-Item -ItemType Directory -Path $zipDir -Force | Out-Null }
$zipFile = Join-Path $zipDir "vexythemes-v$Version.zip"
if (Test-Path $zipFile) { Remove-Item $zipFile -Force }

$zip = [System.IO.Compression.ZipFile]::Create($zipFile)
$sourceDir = $themePath
Get-ChildItem -Path $sourceDir -Recurse -File | ForEach-Object {
    $rel = $_.FullName.Replace($sourceDir + "\", "")
    $entry = $zip.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
    $writer = New-Object System.IO.StreamWriter($entry.Open())
    $writer.Write([System.IO.File]::ReadAllText($_.FullName))
    $writer.Close()
}
$zip.Dispose()
Write-Host "  Zip created: $zipFile" -ForegroundColor Green

# Step 3: Calculate checksum
Write-Host "[3/5] Calculating checksum..." -ForegroundColor Yellow
$zipBytes = [System.IO.File]::ReadAllBytes($zipFile)
$sha = [System.Security.Cryptography.SHA256]::Create()
$hash = $sha.ComputeHash($zipBytes)
$checksum = [BitConverter]::ToString($hash) -replace '-', ''
Write-Host "  Checksum: $checksum" -ForegroundColor Green

# Step 4: Upload to server
Write-Host "[4/5] Uploading to update server..." -ForegroundColor Yellow
$zipBase64 = [Convert]::ToBase64String($zipBytes)
$zipSize = (Get-Item $zipFile).Length

# Set version info
$versionPayload = @{
    _endpoint = "admin"
    action = "set_version"
    session = $Session
    version = $Version
    released = (Get-Date -Format "yyyy-MM-dd")
    changelog = $Changelog
    checksum = $checksum
    size = $zipSize
} | ConvertTo-Json

$versionRes = Invoke-RestMethod -Uri "$apiUrl/api/index" -Method Post -Body $versionPayload -ContentType "application/json"
if ($versionRes.success) {
    Write-Host "  Version info uploaded" -ForegroundColor Green
} else {
    Write-Host "  Failed to set version: $($versionRes.error)" -ForegroundColor Red
    exit 1
}

# Upload zip
$zipPayload = @{
    _endpoint = "admin"
    action = "upload_update"
    session = $Session
    version = $Version
    zip_data = $zipBase64
} | ConvertTo-Json

$zipRes = Invoke-RestMethod -Uri "$apiUrl/api/index" -Method Post -Body $zipPayload -ContentType "application/json"
if ($zipRes.success) {
    Write-Host "  Update zip uploaded" -ForegroundColor Green
} else {
    Write-Host "  Failed to upload zip: $($zipRes.error)" -ForegroundColor Red
    exit 1
}

# Step 5: Summary
Write-Host ""
Write-Host "[5/5] Done!" -ForegroundColor Green
Write-Host "  Version: v$Version"
Write-Host "  Checksum: $checksum"
Write-Host "  Size: $([math]::Round($zipSize / 1MB, 2)) MB"
Write-Host ""
Write-Host "All customer panels will receive this update automatically." -ForegroundColor Cyan
