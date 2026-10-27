<?php
define('BASE_PATH', dirname(__DIR__));
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$tables = Database::fetchAll("SHOW TABLES");
foreach ($tables as $row) {
    echo array_values($row)[0] . "\n";
}
