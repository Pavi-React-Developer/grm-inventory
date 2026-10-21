$targets = @('C:\xampp\htdocs\grm_inventory', 'C:\xampp\htdocs\grm_inventory\grm_inventory')
$files = @(
    'public\assets\js\pagination.js',
    'public\assets\js\app.js',
    'public\assets\css\style.css',
    'views\layouts\main.php',
    'views\production\batches\index.php',
    'views\production\sellers\index.php',
    'views\production\damages\index.php',
    'views\production\catalog\index.php',
    'views\production\programs\index.php',
    'views\production\cutting\index.php',
    'views\production\stitching\index.php',
    'views\production\stitching_wastage\index.php',
    'views\production\wastage\index.php',
    'views\production\expenses\index.php',
    'views\production\labels\index.php',
    'views\production\qc\index.php',
    'views\production\outward\index.php',
    'views\production\inventory\index.php'
)

foreach ($t in $targets) {
    if (Test-Path $t) {
        Write-Host "Syncing to $t"
        foreach ($f in $files) {
            $src = Join-Path "c:\Users\ADMIN\Downloads\grm_inventory\grm_inventory" $f
            $dest = Join-Path $t $f
            $dir = Split-Path $dest -Parent
            if (-not (Test-Path $dir)) {
                New-Item -ItemType Directory -Path $dir -Force | Out-Null
            }
            Copy-Item -Path $src -Destination $dest -Force
            Write-Host "Copied $f -> $dest"
        }
    } else {
        Write-Host "Path does not exist: $t"
    }
}
Write-Host "Sync completed successfully!"
