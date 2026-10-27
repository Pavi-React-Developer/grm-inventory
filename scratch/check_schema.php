<?php
define('BASE_PATH', dirname(__DIR__));
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$tables = ['warehouse_categories', 'categories', 'products', 'product_variants', 'sales', 'sale_items', 'purchase_orders', 'purchase_items', 'seller_items'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    try {
        $cols = Database::fetchAll("DESCRIBE `$t`");
        foreach ($cols as $c) {
            echo "  {$c['Field']} ({$c['Type']})\n";
        }
    } catch (Exception $e) {
        echo "  Error: " . $e->getMessage() . "\n";
    }
}
