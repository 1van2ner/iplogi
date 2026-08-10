<?php
require 'includes/config.php';
$pdo = getDB();
$cols = $pdo->query("SHOW COLUMNS FROM detalle_pedidos")->fetchAll(PDO::FETCH_ASSOC);
$hasEstado = false;
foreach ($cols as $col) {
    if (($col['Field'] ?? '') === 'estado') {
        $hasEstado = true;
        break;
    }
}
if (!$hasEstado) {
    $pdo->exec("ALTER TABLE detalle_pedidos ADD COLUMN estado VARCHAR(50) NOT NULL DEFAULT 'pendiente' AFTER subtotal");
    echo "detalle_pedidos.estado added\n";
} else {
    echo "detalle_pedidos.estado already exists\n";
}
