<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$pdo = Database::getInstance();

echo "MariaDB Version: " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Current Table Count: " . count($tables) . "\n";

// Check schema.sql tables
$schemaSql = file_get_contents(BASE_PATH . '/database/schema.sql');
preg_match_all('/CREATE TABLE `([^`]+)`/i', $schemaSql, $matches);
$schemaTables = array_unique($matches[1]);
echo "Schema.sql Table Count: " . count($schemaTables) . "\n";

$missingTables = array_diff($schemaTables, $tables);
echo "Missing Tables (" . count($missingTables) . "):\n";
foreach ($missingTables as $mt) {
    echo "  - $mt\n";
}

// Check row counts of existing tables
echo "\nSample table row counts:\n";
foreach (['roles', 'permissions', 'users', 'products', 'product_variants', 'shops', 'warehouses', 'categories', 'sizes', 'colors'] as $t) {
    if (in_array($t, $tables)) {
        $c = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  - $t: $c rows\n";
    } else {
        echo "  - $t: MISSING\n";
    }
}
