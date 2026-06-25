$src = "C:\Users\akshi\Desktop\pterodactyl-base\app"
$dst = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app"

# Directories to copy entirely from base Pterodactyl
$dirs = @(
    "Console\Commands",
    "Console",
    "Contracts",
    "Enum",
    "Events",
    "Exceptions",
    "Extensions",
    "Facades",
    "Helpers",
    "Http\Kernel.php",
    "Http\Middleware",
    "Http\Requests",
    "Http\Resources",
    "Http\ViewComposers",
    "Jobs",
    "Listeners",
    "Mail",
    "Models",
    "Notifications",
    "Observers",
    "Policies",
    "Providers",
    "Repositories",
    "Rules",
    "Services",
    "Traits",
    "Transformers"
)

$count = 0
foreach ($d in $dirs) {
    $srcDir = Join-Path $src $d
    $dstDir = Join-Path $dst $d
    
    if ($d.EndsWith(".php")) {
        # Single file
        if (Test-Path $srcDir) {
            $dstFileDir = Split-Path $dstDir
            if (!(Test-Path $dstFileDir)) { New-Item -ItemType Directory -Path $dstFileDir -Force | Out-Null }
            Copy-Item -Path $srcDir -Destination $dstDir -Force
            $count++
        }
    } else {
        # Directory
        if (Test-Path $srcDir) {
            if (!(Test-Path $dstDir)) { New-Item -ItemType Directory -Path $dstDir -Force | Out-Null }
            Copy-Item -Path "$srcDir\*" -Destination $dstDir -Recurse -Force
            $files = (Get-ChildItem -Path $dstDir -Recurse -File).Count
            $count += $files
            Write-Host "Copied: $d ($files files)"
        }
    }
}

# Also copy helpers.php
$helpersSrc = Join-Path $src "helpers.php"
$helpersDst = Join-Path $dst "helpers.php"
if (Test-Path $helpersSrc) {
    Copy-Item -Path $helpersSrc -Destination $helpersDst -Force
    Write-Host "Copied: helpers.php"
    $count++
}

Write-Host ""
Write-Host "Total files copied: $count"
