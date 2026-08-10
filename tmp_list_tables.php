<?php
require_once 'includes/config.php';
try {
    $pdo = getDB();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo $table . "\n";
    }
} catch (Exception $e) {
    echo 'ERR: ' . $e->getMessage();
}
