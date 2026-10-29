<?php
/**
 * Migration: Store selected QC piece QR codes with packed batch items.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $db = Database::getInstance();
    $columns = array_column(Database::fetchAll("DESCRIBE `production_batch_items`"), 'Field');
    if (!in_array('piece_tags', $columns, true)) {
        $db->exec("ALTER TABLE `production_batch_items` ADD COLUMN `piece_tags` LONGTEXT NULL AFTER `quantity`");
    }
    echo "Production batch QR tag migration completed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Production batch QR tag migration failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
