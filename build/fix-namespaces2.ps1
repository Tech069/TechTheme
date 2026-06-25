$base = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app"
$count = 0

Get-ChildItem -Path $base -Recurse -Filter "*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content -and $content -match "namespace App\\") {
        $relPath = $_.FullName.Replace($base + "\", "") -replace "\\", "/"
        $parts = $relPath -split "/"
        $dir = $parts[0..($parts.Count - 2)] -join "/"
        $correctNs = "Pterodactyl\\"
        if ($dir) {
            $correctNs += ($dir -replace "/", "\\")
        }
        
        # Use regex to replace namespace line
        $newContent = [regex]::Replace($content, "namespace App\\[^;]+;", "namespace $correctNs;")
        $newContent = $newContent -replace "extends App\\Models\\Model", "extends Pterodactyl\\Models\\Model"
        $newContent = $newContent -replace "extends App\\Http\\Controllers\\Controller", "extends Pterodactyl\\Http\\Controllers\\Controller"
        
        if ($newContent -ne $content) {
            [System.IO.File]::WriteAllText($_.FullName, $newContent)
            $count++
        }
    }
}

Write-Host "Fixed namespace in $count files"
