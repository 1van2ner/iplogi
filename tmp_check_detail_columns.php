<?php
require 'includes/config.php';
$pdo = getDB();
$cols = $pdo->query('SHOW COLUMNS FROM detalle_pedidos')->fetchAll(PDO::FETCH_ASSOC);
$hasEstado = false;
foreach ($cols as $col) {
    if ($col['Field'] === 'estado') {
        $hasEstado = true;
        break;
    }
}
echo $hasEstado ? 'estado_exists' : 'estado_missing';
