<?php
require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

try {
    $cols = Database::fetchAll("DESCRIBE label_items");
    $colNames = array_column($cols, 'Field');
    
    if (!in_array('start_seq', $colNames)) {
        Database::execute("ALTER TABLE label_items ADD COLUMN start_seq INT(11) DEFAULT 1 AFTER damaged_qty");
        echo "Added start_seq column.\n";
    }
    if (!in_array('end_seq', $colNames)) {
        Database::execute("ALTER TABLE label_items ADD COLUMN end_seq INT(11) DEFAULT 1 AFTER start_seq");
        echo "Added end_seq column.\n";
    }
    if (!in_array('start_code', $colNames)) {
        Database::execute("ALTER TABLE label_items ADD COLUMN start_code VARCHAR(50) DEFAULT NULL AFTER end_seq");
        echo "Added start_code column.\n";
    }
    if (!in_array('end_code', $colNames)) {
        Database::execute("ALTER TABLE label_items ADD COLUMN end_code VARCHAR(50) DEFAULT NULL AFTER start_code");
        echo "Added end_code column.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
