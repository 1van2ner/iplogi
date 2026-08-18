<?php
require_once dirname(__DIR__) . '/includes/config.php';
requireLogin();
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$pdo = getDB();
$id = (int)($_POST['pedido_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de pedido inválido']);
    exit;
}

try {
    $pdo->beginTransaction();
    // Optionally, restore stock: increase productos.stock by cantidad in detalle_pedidos
    $rows = $pdo->prepare('SELECT producto_id, cantidad FROM detalle_pedidos WHERE pedido_id = ?');
    $rows->execute([$id]);
    $items = $rows->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $it) {
        if (!empty($it['producto_id']) && !empty($it['cantidad'])) {
            $pdo->prepare('UPDATE productos SET stock = stock + ? WHERE id = ?')->execute([(int)$it['cantidad'], (int)$it['producto_id']]);
        }
    }

    $pdo->prepare('DELETE FROM detalle_pedidos WHERE pedido_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM pedidos WHERE id = ?')->execute([$id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('eliminar-pedido error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar pedido']);
    exit;
}

?>
