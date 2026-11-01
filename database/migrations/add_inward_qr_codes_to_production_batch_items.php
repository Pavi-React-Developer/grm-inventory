<?php
/**
 * Store exact received and damaged QR codes for production batch inward records.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $db = Database::getInstance();
    $table = 'production_batch_items';
    $existingColumns = array_column(Database::fetchAll("SHOW COLUMNS FROM `$table`"), 'Field');
    $columnsToAdd = [
        'received_qr_codes' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `missing_qty`',
        'damaged_qr_codes' => 'LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER `received_qr_codes`',
    ];

    foreach ($columnsToAdd as $column => $definition) {
        if (in_array($column, $existingColumns, true)) {
            echo "Verified `$table`.`$column`.\n";
            continue;
        }

        $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        $existingColumns[] = $column;
        echo "Added `$table`.`$column`.\n";
    }

    echo "SUCCESS: Production inward QR code columns are ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database Error: " . $e->getMessage() . "\n");
    exit(1);
}
