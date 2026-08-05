<?php
require_once 'includes/config.php';
$pdo = getDB();
$stmt = $pdo->query('SHOW COLUMNS FROM usuarios');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo $col['Field'] . ' ' . $col['Type'] . ' ' . ($col['Null'] ?? '') . ' ' . ($col['Default'] === null ? 'NULL' : $col['Default']) . ' ' . ($col['Extra'] ?? '') . "\n";
}
