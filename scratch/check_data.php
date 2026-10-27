<?php
define('BASE_PATH', dirname(__DIR__));
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

echo "--- Latest Allocations ---\n";
$allocs = Database::fetchAll("SELECT * FROM warehouse_allocations ORDER BY id DESC LIMIT 3");
print_r($allocs);

echo "--- Allocation Items ---\n";
if (!empty($allocs)) {
    $items = Database::fetchAll("SELECT * FROM allocation_items WHERE allocation_id = ?", [$allocs[0]['id']]);
    print_r($items);
}

echo "--- Allocation Outwards ---\n";
if (!empty($allocs)) {
    $outwards = Database::fetchAll("SELECT * FROM allocation_outwards WHERE allocation_id = ?", [$allocs[0]['id']]);
    print_r($outwards);
}
