<?php
/**
 * Add catalog codes and supplier document fields used by warehouse workflows.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $db = Database::getInstance();
    $columnsToAdd = [
        'products' => [
            'catalog_code' => 'VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `code`',
        ],
        'warehouse_suppliers' => [
            'document_url' => 'VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `material_type`',
            'notes' => 'TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `document_url`',
        ],
    ];

    foreach ($columnsToAdd as $table => $columns) {
        $existingColumns = array_column(Database::fetchAll("SHOW COLUMNS FROM `$table`"), 'Field');
        foreach ($columns as $column => $definition) {
            if (in_array($column, $existingColumns, true)) {
                echo "Verified `$table`.`$column`.\n";
                continue;
            }

            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            $existingColumns[] = $column;
            echo "Added `$table`.`$column`.\n";
        }
    }

    echo "SUCCESS: Catalog and supplier columns are ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database Error: " . $e->getMessage() . "\n");
    exit(1);
}
