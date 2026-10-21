<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$res = Database::fetchAll("SELECT id, name, batch_number, cut_qty, program_id FROM cutting_batches WHERE batch_number LIKE '%20261013-0021%'");
print_r($res);

if (!empty($res)) {
    $batchId = $res[0]['id'];
    $items = Database::fetchAll("SELECT * FROM cutting_items WHERE cutting_batch_id = ?", [$batchId]);
    echo "Cutting items count: " . count($items) . "\n";
    print_r($items);
}
