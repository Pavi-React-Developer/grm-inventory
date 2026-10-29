<?php
/**
 * Migration: Store QR tags for replacement items in exchanges.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $db = Database::getInstance();
    $columns = array_column(Database::fetchAll("DESCRIBE `exchange_items`"), 'Field');
    if (!in_array('piece_tag', $columns, true)) {
        $db->exec("ALTER TABLE `exchange_items` ADD COLUMN `piece_tag` VARCHAR(255) NULL AFTER `total_amount`");
    }
    echo "Exchange item QR tag migration completed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Exchange item QR tag migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
