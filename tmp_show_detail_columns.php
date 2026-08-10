<?php
require 'includes/config.php';
$pdo = getDB();
$cols = $pdo->query('SHOW COLUMNS FROM detalle_pedidos')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo $col['Field'] . "\n";
}
