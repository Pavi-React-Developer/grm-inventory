<?php
define('BASE_PATH', dirname(__DIR__));
$appConfig = require BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

foreach (['label_batches', 'label_items', 'stock_balances', 'stock_transactions'] as $t) {
    echo "=== TABLE: $t ===\n";
    $cols = Database::fetchAll("DESCRIBE `$t`");
    foreach ($cols as $c) {
        echo "  {$c['Field']} ({$c['Type']})\n";
    }
}
