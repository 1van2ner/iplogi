<?php
require 'includes/config.php';
$pdo = getDB();
foreach (['pedidos', 'detalle_pedidos'] as $t) {
    $stmt = $pdo->query('SHOW CREATE TABLE `'.$t.'`');
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "TABLE $t\n";
    echo $r['Create Table']."\n\n";
}
