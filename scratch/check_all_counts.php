<?php
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/app/Helpers/Database.php';

$pdo = Database::getInstance();
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

echo "Total tables in database: " . count($tables) . "\n\n";

$nonEmpty = 0;
$empty = 0;

$results = [];
foreach ($tables as $t) {
    try {
        $c = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $results[$t] = $c;
        if ($c > 0) {
            $nonEmpty++;
        } else {
            $empty++;
        }
    } catch (Throwable $e) {
        $results[$t] = "ERR: " . $e->getMessage();
    }
}

ksort($results);
foreach ($results as $t => $c) {
    if ($c > 0) {
        printf("✓ %-32s : %6d rows\n", $t, $c);
    }
}

echo "\nTables with 0 rows:\n";
foreach ($results as $t => $c) {
    if ($c === 0 || $c === '0') {
        echo "  - $t\n";
    }
}

echo "\nSummary: $nonEmpty tables populated, $empty tables empty.\n";
