<?php
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';
require_once BASE_PATH . '/app/Helpers/Response.php';
require_once BASE_PATH . '/app/Models/CuttingBatch.php';
require_once BASE_PATH . '/app/Models/CuttingItem.php';
require_once BASE_PATH . '/app/Models/ProductionProgram.php';
require_once BASE_PATH . '/app/Controllers/ProductionController.php';

$controller = new ProductionController();
ob_start();
$controller->ajaxCuttingDetails(21);
$output = ob_get_clean();
echo $output;
