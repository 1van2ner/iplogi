<?php
require_once '../includes/config.php';
requireLogin();
// Permitir acceso a administradores y al rol de reparto (repartidor)
if (!isAdmin() && ($_SESSION['rol'] ?? '') !== 'repartidor') {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Acceso denegado']);
    exit;
}
header('Content-Type: application/json');

$pdo = getDB();

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
$accion   = $_POST['accion'] ?? 'estado';

if (!$pedidoId) {
    echo json_encode(['success'=>false,'message'=>'ID de pedido inválido']);
    exit;
}

// Verificar que el pedido existe
$pedido = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
$pedido->execute([$pedidoId]);
$pedido = $pedido->fetch();

if (!$pedido) {
    echo json_encode(['success'=>false,'message'=>'Pedido no encontrado']);
    exit;
}

switch ($accion) {

    // ── Cambiar estado del pedido (global) ──────────────────────────────────────
    case 'estado':
        $estadosValidos = ['pendiente','confirmado','procesando','enviado','entregado','cancelado'];
        $nuevoEstado = $_POST['estado'] ?? '';

        if (!in_array($nuevoEstado, $estadosValidos)) {
            echo json_encode(['success'=>false,'message'=>'Estado no válido']);
            exit;
        }

        // Si se cancela, devolver stock
        if ($nuevoEstado === 'cancelado' && $pedido['estado'] !== 'cancelado') {
            $detalles = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_pedidos WHERE pedido_id = ?");
            $detalles->execute([$pedidoId]);
            foreach ($detalles->fetchAll() as $det) {
                $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?")
                    ->execute([$det['cantidad'], $det['producto_id']]);
            }
        }

        // Si se reactiva desde cancelado, descontar stock nuevamente
        if ($pedido['estado'] === 'cancelado' && $nuevoEstado !== 'cancelado') {
            $detalles = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_pedidos WHERE pedido_id = ?");
            $detalles->execute([$pedidoId]);
            foreach ($detalles->fetchAll() as $det) {
                $pdo->prepare("UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id = ?")
                    ->execute([$det['cantidad'], $det['producto_id']]);
            }
        }

        $pdo->prepare("UPDATE pedidos SET estado = ?, actualizado_en = NOW() WHERE id = ?")
            ->execute([$nuevoEstado, $pedidoId]);

        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado a: ' . ucfirst($nuevoEstado),
            'estado'  => $nuevoEstado,
        ]);
        break;

    // ── Cambiar estado de una línea de producto ──────────────────────────────────────
    case 'producto_estado':
        $detalleId = (int)($_POST['detalle_id'] ?? 0);
        $nuevoEstado = $_POST['estado'] ?? '';
        $estadosValidos = ['pendiente','confirmado','procesando','enviado','entregado','cancelado'];

        if (!$detalleId || !in_array($nuevoEstado, $estadosValidos, true)) {
            echo json_encode(['success'=>false,'message'=>'Detalle o estado inválido']);
            exit;
        }

        $check = $pdo->prepare("SELECT id FROM detalle_pedidos WHERE id = ? AND pedido_id = ?");
        $check->execute([$detalleId, $pedidoId]);
        if (!$check->fetch()) {
            echo json_encode(['success'=>false,'message'=>'Detalle de pedido no encontrado']);
            exit;
        }

        $pdo->prepare("UPDATE detalle_pedidos SET estado = ? WHERE id = ? AND pedido_id = ?")
            ->execute([$nuevoEstado, $detalleId, $pedidoId]);

        echo json_encode([
            'success' => true,
            'message' => 'Estado de producto actualizado',
            'estado' => $nuevoEstado,
            'detalle_id' => $detalleId,
        ]);
        break;

    // ── Actualizar datos de entrega ─────────────────────────
    case 'entrega':
        $direccion  = sanitize($_POST['direccion']  ?? '');
        $distrito   = sanitize($_POST['distrito']   ?? '');
        $referencia = sanitize($_POST['referencia'] ?? '');

        $pdo->prepare("UPDATE pedidos SET direccion_entrega = ?, distrito_entrega = ?, referencia = ?, actualizado_en = NOW() WHERE id = ?")
            ->execute([$direccion, $distrito, $referencia, $pedidoId]);

        echo json_encode(['success'=>true,'message'=>'Datos de entrega actualizados']);
        break;

    // ── Agregar nota interna ────────────────────────────────
    case 'nota':
        $nota = sanitize($_POST['nota'] ?? '');
        if (!$nota) { echo json_encode(['success'=>false,'message'=>'La nota no puede estar vacía']); exit; }

        $pdo->prepare("UPDATE pedidos SET notas_admin = CONCAT_WS('\n', notas_admin, ?), actualizado_en = NOW() WHERE id = ?")
            ->execute([$nota . ' [' . date('d/m/Y H:i') . ']', $pedidoId]);

        echo json_encode(['success'=>true,'message'=>'Nota agregada correctamente']);
        break;

    // ── Eliminar pedido (solo admin superadmin) ─────────────
    case 'eliminar':
        // Solo administradores pueden eliminar pedidos definitivamente
        if (!isAdmin()) {
            echo json_encode(['success'=>false,'message'=>'Solo administradores pueden eliminar pedidos']);
            exit;
        }
        if ($pedido['estado'] !== 'cancelado') {
            echo json_encode(['success'=>false,'message'=>'Solo se pueden eliminar pedidos cancelados']);
            exit;
        }
        $pdo->prepare("DELETE FROM detalle_pedidos WHERE pedido_id = ?")->execute([$pedidoId]);
        $pdo->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$pedidoId]);

        echo json_encode(['success'=>true,'message'=>'Pedido eliminado correctamente']);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Acción no reconocida']);
        break;
}
?>
