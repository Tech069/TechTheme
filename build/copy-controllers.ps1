$src = "C:\Users\akshi\Desktop\pterodactyl-base\app\Http\Controllers"
$dst = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app\Http\Controllers"

$dirs = @("Admin", "Api\Application", "Api\Remote", "Auth", "Base")
foreach ($d in $dirs) {
    $srcDir = Join-Path $src $d
    $dstDir = Join-Path $dst $d
    if (Test-Path $srcDir) {
        if (!(Test-Path $dstDir)) { New-Item -ItemType Directory -Path $dstDir -Force | Out-Null }
        Copy-Item -Path "$srcDir\*" -Destination $dstDir -Recurse -Force
        Write-Host "Copied: $d"
    }
}
Copy-Item -Path "$src\Controller.php" -Destination "$dst\Controller.php" -Force
Write-Host "Copied: Controller.php"

$clientSrc = Join-Path $src "Api\Client"
$clientDst = Join-Path $dst "Api\Client"
$clientFiles = @("ClientController.php", "ClientApiController.php", "AccountController.php", "TwoFactorController.php", "ApiKeyController.php", "SSHKeyController.php", "ActivityLogController.php")
foreach ($f in $clientFiles) {
    $sf = Join-Path $clientSrc $f
    $df = Join-Path $clientDst $f
    if (Test-Path $sf) { Copy-Item -Path $sf -Destination $df -Force; Write-Host "Copied: Client/$f" }
}
$serversSrc = Join-Path $clientSrc "Servers"
$serversDst = Join-Path $clientDst "Servers"
if (Test-Path $serversSrc) {
    Get-ChildItem -Path $serversSrc -File -Filter "*.php" | ForEach-Object {
        Copy-Item -Path $_.FullName -Destination $serversDst -Force
        Write-Host "Copied: Client/Servers/$($_.Name)"
    }
}
Write-Host "ALL DONE"
