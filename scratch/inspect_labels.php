<?php
require __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/Helpers/Database.php';

$tables = Database::fetchAll("SHOW TABLES");
foreach ($tables as $t) {
    $tbl = array_values($t)[0];
    if (strpos($tbl, 'label') !== false) {
        echo "TABLE: $tbl\n";
        $cols = Database::fetchAll("DESCRIBE `$tbl`");
        foreach ($cols as $c) {
            echo "  {$c['Field']} ({$c['Type']})\n";
        }
    }
}
