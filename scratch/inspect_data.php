<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

$wh = Database::fetchAll('SELECT id, name, code FROM warehouses');
echo "Warehouses:\n" . json_encode($wh, JSON_PRETTY_PRINT) . "\n\n";

$out = Database::fetchAll('SELECT id, outward_number, production_batch_id, warehouse_id, quantity, dispatch_date FROM production_outwards LIMIT 5');
echo "Outwards:\n" . json_encode($out, JSON_PRETTY_PRINT) . "\n\n";

$batches = Database::fetchAll('SELECT id, batch_number, destination_warehouse_id, total_qty, status FROM production_batches LIMIT 5');
echo "Batches:\n" . json_encode($batches, JSON_PRETTY_PRINT) . "\n\n";

$wd = Database::fetchAll("SELECT id, damage_number, warehouse_id, variant_id, quantity, status FROM warehouse_damages WHERE status = 'REWORK' OR damage_number LIKE 'RWK%' LIMIT 5");
echo "Existing Rework in warehouse_damages:\n" . json_encode($wd, JSON_PRETTY_PRINT) . "\n\n";
