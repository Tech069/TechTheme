$dir = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app\Http\Controllers"
$encoded = Get-ChildItem -Path $dir -Recurse -Filter "*.php" | Where-Object { (Get-Content $_.FullName -First 1 -ErrorAction SilentlyContinue) -match "AVTIX_ENCODED" }
Write-Host "ENCODED files:"
$encoded | ForEach-Object { 
    $rel = $_.FullName.Replace($dir + "\", "")
    Write-Host "  $rel" 
}
Write-Host ""
Write-Host "Total encoded: $($encoded.Count)"
