$dir = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app\Http\Controllers"
$routeFile = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\routes\api-client.php"

$content = Get-Content $routeFile -Raw

# Extract class references
$matches = [regex]::Matches($content, 'use Pterodactyl\\Http\\Controllers\\(Api\\Client\\[^;]+);')
$classes = @()
foreach ($m in $matches) {
    $classes += $m.Groups[1].Value
}

# Also get inline references
$inlineMatches = [regex]::Matches($content, '\[(Pterodactyl\\Http\\Controllers\\[^:]+)::class')
foreach ($m in $inlineMatches) {
    $classes += $m.Groups[1].Value
}

$classes = $classes | Sort-Object -Unique

Write-Host "Referenced controller classes:"
$missing = @()
foreach ($c in $classes) {
    $relativePath = $c.Replace("Pterodactyl\Http\Controllers\", "") + ".php"
    $fullPath = Join-Path $dir $relativePath
    if (!(Test-Path $fullPath)) {
        Write-Host "  MISSING: $c"
        $missing += $c
    }
}
Write-Host ""
Write-Host "Total missing: $($missing.Count)"
