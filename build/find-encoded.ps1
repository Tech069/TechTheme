$dir = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app\Http\Controllers"
$count = 0
Get-ChildItem -Path $dir -Recurse -Filter "*.php" | ForEach-Object {
    $first = Get-Content $_.FullName -First 1 -ErrorAction SilentlyContinue
    if ($first -match "AVTIX_ENCODED") {
        $rel = $_.FullName.Replace($dir + "\", "")
        Write-Host "ENCODED: $rel"
        $count++
    }
}
Write-Host ""
Write-Host "Total encoded files: $count"
