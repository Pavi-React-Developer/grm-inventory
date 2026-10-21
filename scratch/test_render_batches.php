<?php
define('BASE_PATH', 'C:/xampp/htdocs/grm_inventory/grm_inventory');
function e($v) { return htmlspecialchars((string)$v); }
function url($p) { return '/' . $p; }
function formatDate($d) { return $d; }
function hasPermission($m, $a) { return true; }
$batches = [];
for ($i = 1; $i <= 23; $i++) {
    $batches[] = [
        'id' => $i,
        'batch_number' => 'BAT-' . $i,
        'created_at' => '2026-10-14',
        'status' => 'APPROVED',
        'total_qty' => 10 * $i,
        'box_count' => 1,
        'warehouse_name' => 'WH1',
        'program_id' => $i,
        'program_number' => 'PRG-' . $i,
        'product_name' => 'Prod ' . $i,
        'destination_warehouse_id' => 1,
        'prepared_by_name' => 'Admin'
    ];
}
$filters = [];
$warehouses = [];
ob_start();
include 'C:/xampp/htdocs/grm_inventory/grm_inventory/views/production/batches/index.php';
$html = ob_get_clean();
echo 'batchesPaginationList present: ' . (strpos($html, 'batchesPaginationList') !== false ? 'YES' : 'NO') . PHP_EOL;
echo 'initClientPagination present: ' . (strpos($html, 'initClientPagination') !== false ? 'YES' : 'NO') . PHP_EOL;

$pos = strpos($html, 'batchesPaginationFooter');
echo substr($html, $pos, 1500) . PHP_EOL;

