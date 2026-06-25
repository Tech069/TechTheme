# VexyThemes - Apply Dev Changes to Customer Panel
# Usage:
#   .\build\apply-to-customer.ps1 -CustomerIP "1.2.3.4" -CustomerPath "/var/www/pterodactyl"
#   Copies all theme changes to a customer's VPS

param(
    [Parameter(Mandatory=$true)]
    [string]$CustomerIP,
    
    [Parameter(Mandatory=$false)]
    [string]$CustomerPath = "/var/www/pterodactyl",
    
    [Parameter(Mandatory=$false)]
    [string]$CustomerUser = "root"
)

$ErrorActionPreference = "Stop"
$themePath = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full"

Write-Host "=== VexyThemes - Apply to Customer ===" -ForegroundColor Cyan
Write-Host "Target: $CustomerUser@$CustomerIP`:$CustomerPath"
Write-Host ""

# Step 1: Create zip of changes
Write-Host "[1/4] Creating update package..." -ForegroundColor Yellow
$tempZip = "$env:TEMP\vexythemes-patch.zip"
if (Test-Path $tempZip) { Remove-Item $tempZip -Force }

Compress-Archive -Path "$themePath\*" -DestinationPath $tempZip -Force
$zipSize = (Get-Item $tempZip).Length
Write-Host "  Package size: $([math]::Round($zipSize / 1MB, 2)) MB" -ForegroundColor Green

# Step 2: Upload to customer
Write-Host "[2/4] Uploading to customer..." -ForegroundColor Yellow
scp $tempZip "${CustomerUser}@${CustomerIP}:/tmp/vexythemes-patch.zip"
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Upload failed!" -ForegroundColor Red
    exit 1
}
Write-Host "  Uploaded" -ForegroundColor Green

# Step 3: Apply on customer server
Write-Host "[3/4] Applying update on customer server..." -ForegroundColor Yellow
$remoteScript = @"
cd $CustomerPath
echo '[1/4] Backing up current theme...'
cp -r resources/themes/vexythemes resources/themes/vexythemes_backup_$(date +%Y%m%d_%H%M%S) 2>/dev/null || true

echo '[2/4] Extracting update...'
cd /tmp
unzip -o vexythemes-patch.zip -d vexythemes_update

echo '[3/4] Copying files...'
cp -r vexythemes_update/* $CustomerPath/

echo '[4/4] Clearing caches...'
cd $CustomerPath
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan event:clear

echo '[5/5] Cleanup...'
rm -rf /tmp/vexythemes_update
rm -f /tmp/vexythemes-patch.zip

echo 'Done! Theme updated successfully.'
"@

ssh "${CustomerUser}@${CustomerIP}" $remoteScript
if ($LASTEXITCODE -ne 0) {
    Write-Host "  Update failed on customer server!" -ForegroundColor Red
    exit 1
}
Write-Host "  Applied successfully" -ForegroundColor Green

# Step 4: Cleanup
Write-Host "[4/4] Cleanup..." -ForegroundColor Yellow
Remove-Item $tempZip -Force

Write-Host ""
Write-Host "Done! Customer panel updated." -ForegroundColor Green
