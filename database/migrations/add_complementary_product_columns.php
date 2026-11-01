<?php
/**
 * Add product classification columns used by production packing and warehouse flows.
 */

define('BASE_PATH', dirname(__DIR__, 2));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

try {
    $db = Database::getInstance();
    $columns = [
        'product_variants' => [
            'is_complementary' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `pack_quantity`",
        ],
        'products' => [
            'is_complementary' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER `source_type`",
        ],
        'warehouse_categories' => [
            'category_type' => "VARCHAR(50) NOT NULL DEFAULT 'NORMAL' AFTER `name`",
        ],
    ];

    foreach ($columns as $table => $tableColumns) {
        $existingColumns = array_column(Database::fetchAll("SHOW COLUMNS FROM `$table`"), 'Field');
        foreach ($tableColumns as $column => $definition) {
            if (in_array($column, $existingColumns, true)) {
                echo "Verified `$table`.`$column`.\n";
                continue;
            }

            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            $existingColumns[] = $column;
            echo "Added `$table`.`$column`.\n";
        }
    }

    echo "SUCCESS: Product classification schema is ready.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database Error: " . $e->getMessage() . "\n");
    exit(1);
}
