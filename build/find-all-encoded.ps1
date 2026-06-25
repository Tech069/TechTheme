$dir = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full"
$count = 0
$encoded = @()
Get-ChildItem -Path $dir -Recurse -Filter "*.php" | ForEach-Object {
    $first = Get-Content $_.FullName -First 1 -ErrorAction SilentlyContinue
    if ($first -match "002cd|ionCube|AVTIX_ENCODED") {
        $rel = $_.FullName.Replace($dir + "\", "")
        $encoded += $rel
        $count++
    }
}
Write-Host "Total ionCube encoded PHP files: $count"
Write-Host ""
# Group by directory
$encoded | ForEach-Object {
    $parts = $_ -split "\\"
    $folder = ($parts[0..($parts.Count - 2)] -join "\")
    [PSCustomObject]@{Folder=$folder; File=$parts[-1]}
} | Group-Object Folder | ForEach-Object {
    Write-Host "$($_.Name) ($($_.Count) files)"
    $_.Group | ForEach-Object { Write-Host "  $($_.File)" }
}
