<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$pdo = Database::getInstance();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "========================================================\n";
echo "  GRM INVENTORY — COMPREHENSIVE DATABASE FEEDER\n";
echo "========================================================\n\n";

// STEP 1: IMPORT SCHEMA.SQL
echo ">>> STEP 1: Importing database/schema.sql ...\n";
$schemaSql = file_get_contents(BASE_PATH . '/database/schema.sql');

// Split SQL by semicolons followed by newline
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$statements = preg_split('/;\s*[\r\n]+/', $schemaSql);
$successTables = 0;
$errors = [];

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
        continue;
    }
    try {
        $pdo->exec($stmt);
        if (preg_match('/CREATE TABLE `?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
            $successTables++;
        }
    } catch (Throwable $e) {
        $errors[] = "Statement Error: " . substr($stmt, 0, 80) . "... => " . $e->getMessage();
    }
}
echo "Schema executed. Tables created/verified: $successTables\n";
if (!empty($errors)) {
    echo "Encountered " . count($errors) . " errors:\n";
    foreach (array_slice($errors, 0, 5) as $err) {
        echo "  - $err\n";
    }
}

// Check total tables in DB now
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Total tables in database now: " . count($tables) . "\n\n";

// STEP 2: RUN INCREMENTAL MIGRATIONS
echo ">>> STEP 2: Running Database Migrations ...\n";
$migrationFiles = glob(BASE_PATH . '/database/migrations/*.php');
foreach ($migrationFiles as $mf) {
    $mName = basename($mf);
    echo "  -> Running migration: $mName\n";
    try {
        include $mf;
    } catch (Throwable $e) {
        echo "     [Warning] Migration $mName: " . $e->getMessage() . "\n";
    }
}
echo "Migrations completed.\n\n";

// STEP 3: IMPORT SEED.SQL
echo ">>> STEP 3: Importing database/seed.sql ...\n";
$seedSql = file_get_contents(BASE_PATH . '/database/seed.sql');
$seedStatements = preg_split('/;\s*[\r\n]+/', $seedSql);
$seedSuccess = 0;
$seedErrors = [];

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
foreach ($seedStatements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt) || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*') || str_starts_with($stmt, 'LOCK TABLES') || str_starts_with($stmt, 'UNLOCK TABLES')) {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $seedSuccess++;
    } catch (Throwable $e) {
        $seedErrors[] = substr($stmt, 0, 60) . "... => " . $e->getMessage();
    }
}
echo "Seed.sql executed ($seedSuccess statements succeeded, " . count($seedErrors) . " skipped/errored).\n\n";

// STEP 4: SEED ADMIN USERS & CORE FACILITIES
echo ">>> STEP 4: Running scripts/seed_admin_login.php ...\n";
try {
    include BASE_PATH . '/scripts/seed_admin_login.php';
} catch (Throwable $e) {
    echo "Error in seed_admin_login: " . $e->getMessage() . "\n";
}

// STEP 5: RESEED ALL PERMISSIONS
echo "\n>>> STEP 5: Running scripts/reseed_all_permissions.php ...\n";
try {
    include BASE_PATH . '/scripts/reseed_all_permissions.php';
} catch (Throwable $e) {
    echo "Error in reseed_all_permissions: " . $e->getMessage() . "\n";
}

// STEP 6: SEED SHOP PRODUCTS & VARIANTS
echo "\n>>> STEP 6: Running scripts/seed_shop_products.php ...\n";
try {
    include BASE_PATH . '/scripts/seed_shop_products.php';
} catch (Throwable $e) {
    echo "Error in seed_shop_products: " . $e->getMessage() . "\n";
}

// STEP 7: SEED COMPREHENSIVE REPORTS DATA (Sales, Purchases, Production, Expenses, etc.)
echo "\n>>> STEP 7: Running scripts/seed_reports_data.php ...\n";
try {
    include BASE_PATH . '/scripts/seed_reports_data.php';
} catch (Throwable $e) {
    echo "Error in seed_reports_data: " . $e->getMessage() . "\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "\n========================================================\n";
echo "  DATABASE FEED SUMMARY\n";
echo "========================================================\n";

$summaryTables = [
    'users'               => 'Users',
    'roles'               => 'User Roles',
    'permissions'         => 'Permissions',
    'role_permissions'    => 'Role Permissions',
    'warehouses'          => 'Warehouses',
    'shops'               => 'Retail Shops',
    'sales_channels'      => 'Sales Channels',
    'production_units'    => 'Production Units',
    'categories'          => 'Product Categories',
    'products'            => 'Master Products',
    'product_variants'    => 'Product Variants & SKUs',
    'stock_balances'      => 'Stock Balances',
    'customers'           => 'Customers',
    'warehouse_suppliers' => 'Warehouse Suppliers',
    'sales'               => 'Sales Invoices',
    'sale_items'          => 'Sale Line Items',
    'purchase_orders'     => 'Purchase Orders',
    'production_programs' => 'Production Programs',
    'cutting_batches'     => 'Cutting Batches',
    'stitching_batches'   => 'Stitching Batches',
    'qc_batches'          => 'QC Inspection Batches',
    'warehouse_allocations'=> 'Warehouse Allocations',
    'expenses'            => 'Expenses',
    'cash_expenses'       => 'Shop Petty Cash Vouchers',
    'settings'            => 'System Settings',
];

foreach ($summaryTables as $tbl => $label) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM `$tbl`")->fetchColumn();
        printf("  %-28s : %6d records\n", $label, $c);
    } catch (Throwable $e) {
        printf("  %-28s : [ERROR] %s\n", $label, $e->getMessage());
    }
}

echo "\nDatabase seeding completed successfully!\n";
