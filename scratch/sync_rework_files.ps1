$targets = @('C:\xampp\htdocs\grm_inventory', 'C:\xampp\htdocs\grm_inventory\grm_inventory')
$files = @(
    'app\Models\ProductionWarehouseRework.php',
    'app\Controllers\ProductionController.php',
    'app\Controllers\WarehouseController.php',
    'app\Controllers\AuthController.php',
    'routes\web.php',
    'views\layouts\main.php',
    'views\production\rework\index.php',
    'database\schema.sql',
    'database\seed_permissions.php',
    'database\migrations\create_production_warehouse_reworks_table.php'
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
