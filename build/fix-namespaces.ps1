$base = "C:\Users\akshi\Desktop\HYPER-KI-MKC\_theme-extract\theme-full\app"
$count = 0

# Find all files with App\ namespace that should be Pterodactyl\
Get-ChildItem -Path $base -Recurse -Filter "*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -ErrorAction SilentlyContinue
    if ($content -match "^namespace App\\") {
        # Derive correct namespace from file path
        $relPath = $_.FullName.Replace($base + "\", "") -replace "\\", "/"
        $dir = ($relPath -split "/")[0..(($relPath -split "/").Count - 2)] -join "/"
        $correctNs = "Pterodactyl\\" + ($dir -replace "/", "\\")
        
        # Replace namespace
        $newContent = $content -replace "^namespace App\\[^;]+;", "namespace $correctNs;"
        
        # Fix extends for models (App\Models\Model -> Pterodactyl\Models\Model)
        $newContent = $newContent -replace "extends App\\Models\\Model", "extends Pterodactyl\\Models\\Model"
        
        # Fix extends for controllers (App\Http\Controllers\Controller -> Pterodactyl\Http\Controllers\Controller)
        $newContent = $newContent -replace "extends App\\Http\\Controllers\\Controller", "extends Pterodactyl\\Http\\Controllers\\Controller"
        
        # Fix use statements that reference App\ incorrectly
        $newContent = $newContent -replace "use App\\Http\\Controllers\\", "use Pterodactyl\\Http\\Controllers\\"
        
        if ($newContent -ne $content) {
            [System.IO.File]::WriteAllText($_.FullName, $newContent)
            $count++
        }
    }
}

Write-Host "Fixed namespace in $count files"
