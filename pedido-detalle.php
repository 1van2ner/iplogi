<?php
require_once 'includes/config.php';
requireLogin();

$pedidoId = (int)($_GET['id'] ?? 0);
if (!$pedidoId) {
    header('Location: ' . SITE_URL . '/mis-pedidos.php');
    exit;
}

$pdo = getDB();
$isAdmin = isAdmin();
$isRepartidor = ($_SESSION['rol'] ?? '') === 'repartidor';

if ($isAdmin || $isRepartidor) {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nombre, u.apellido, u.email, u.telefono, p.direccion_entrega, p.distrito_entrega, p.referencia, p.notas
         FROM pedidos p
         JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.id = ?"
    );
    $stmt->execute([$pedidoId]);
} else {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nombre, u.apellido, u.email, u.telefono, p.direccion_entrega, p.distrito_entrega, p.referencia, p.notas
         FROM pedidos p
         JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.id = ? AND p.usuario_id = ?"
    );
    $stmt->execute([$pedidoId, $_SESSION['usuario_id']]);
}

$pedido = $stmt->fetch();
if (!$pedido) {
    header('Location: ' . SITE_URL . '/mis-pedidos.php');
    exit;
}

$detalleStmt = $pdo->prepare(
    "SELECT dp.*, pr.nombre AS producto_nombre, pr.marca AS producto_marca, pr.imagen AS producto_imagen, c.icono
     FROM detalle_pedidos dp
     LEFT JOIN productos pr ON dp.producto_id = pr.id
     LEFT JOIN categorias c ON pr.categoria_id = c.id
     WHERE dp.pedido_id = ?"
);
$detalleStmt->execute([$pedidoId]);
$detalles = $detalleStmt->fetchAll();

$pageTitle = 'Detalle de Pedido #' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT);
include 'includes/header.php';
?>

<div class="container" style="padding:24px 20px;">
    <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:800;">Detalle de pedido <span style="color:var(--primary);">#<?= str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) ?></span></h1>
        <div style="font-size:13px;color:var(--gray);margin-top:2px;">
            <a href="<?= SITE_URL ?>/mis-pedidos.php">Mis Pedidos</a> › Detalle
        </div>
    </div>

    <div class="admin-orders-top" style="padding:18px 22px;">
        <div>
            <h2 style="margin:0;font-size:18px;">Estado del pedido</h2>
            <p style="margin:8px 0 0;color:var(--gris3);font-size:14px;">Pedido generado por <?= sanitize($pedido['nombre'] . ' ' . $pedido['apellido']) ?>.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <span style="display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:700;"><?= ucfirst($pedido['estado']) ?></span>
            <span style="background:rgba(34,197,94,.1);color:#134e4a;padding:10px 16px;border-radius:999px;font-weight:700;">Total: <?= formatPrice($pedido['total']) ?></span>
        </div>
    </div>

    <div class="admin-orders-table-wrap" style="margin-top:20px;padding:20px;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
            <div style="background:#fff;border:1px solid rgba(226,232,240,.9);border-radius:20px;padding:18px;">
                <h3 style="margin:0 0 12px;font-size:16px;color:var(--gris1);">Información de cliente</h3>
                <div style="display:grid;gap:10px;font-size:14px;color:var(--gris3);">
                    <div><strong>Cliente:</strong> <?= sanitize($pedido['nombre'] . ' ' . $pedido['apellido']) ?></div>
                    <div><strong>Email:</strong> <?= sanitize($pedido['email']) ?></div>
                    <?php if (!empty($pedido['telefono'])): ?><div><strong>Teléfono:</strong> <?= sanitize($pedido['telefono']) ?></div><?php endif; ?>
                </div>
            </div>
            <div style="background:#fff;border:1px solid rgba(226,232,240,.9);border-radius:20px;padding:18px;">
                <h3 style="margin:0 0 12px;font-size:16px;color:var(--gris1);">Entrega y pago</h3>
                <div style="display:grid;gap:10px;font-size:14px;color:var(--gris3);">
                    <div><strong>Tipo entrega:</strong> <?= $pedido['tipo_entrega'] === 'delivery' ? 'Delivery' : 'Recojo en tienda' ?></div>
                    <?php if (!empty($pedido['distrito_entrega']) || !empty($pedido['direccion_entrega'])): ?>
                        <div><strong>Dirección:</strong> <?= sanitize(trim(($pedido['distrito_entrega'] ?? '') . ' ' . ($pedido['direccion_entrega'] ?? ''))) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($pedido['referencia'])): ?><div><strong>Referencia:</strong> <?= sanitize($pedido['referencia']) ?></div><?php endif; ?>
                    <?php if (!empty($pedido['notas'])): ?><div><strong>Notas:</strong> <?= sanitize($pedido['notas']) ?></div><?php endif; ?>
                    <div><strong>Método pago:</strong> <?= ucfirst($pedido['metodo_pago'] ?? 'N/A') ?></div>
                </div>
            </div>
        </div>

        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:#f8fbff;color:var(--gris2);text-align:left;">
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);">Producto</th>
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);">Marca</th>
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);text-align:center;">Cantidad</th>
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);text-align:right;">Precio</th>
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);text-align:right;">Subtotal</th>
                    <th style="padding:14px 16px;border-bottom:1px solid rgba(226,232,240,.95);text-align:center;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $item): ?>
                    <tr style="border-bottom:1px solid rgba(226,232,240,.85);">
                        <td style="padding:14px 16px;vertical-align:middle;"><?= sanitize($item['producto_nombre'] ?: 'Sin nombre') ?></td>
                        <td style="padding:14px 16px;vertical-align:middle;"><?= sanitize($item['producto_marca'] ?: '-') ?></td>
                        <td style="padding:14px 16px;vertical-align:middle;text-align:center;"><?= (int)$item['cantidad'] ?></td>
                        <td style="padding:14px 16px;vertical-align:middle;text-align:right;"><?= formatPrice($item['precio_unitario']) ?></td>
                        <td style="padding:14px 16px;vertical-align:middle;text-align:right;"><?= formatPrice($item['subtotal']) ?></td>
                        <td style="padding:14px 16px;vertical-align:middle;text-align:center;"><?= ucfirst($item['estado'] ?? 'pendiente') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="display:flex;justify-content:flex-end;margin-top:18px;gap:14px;">
            <div style="background:#f8fafc;color:var(--gris2);padding:12px 18px;border-radius:14px;">Items: <?= count($detalles) ?></div>
            <div style="background:rgba(59,130,246,.08);color:var(--gris1);padding:12px 18px;border-radius:14px;font-weight:700;">Total: <?= formatPrice($pedido['total']) ?></div>
        </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/mis-pedidos.php" class="btn btn-secondary">Volver a mis pedidos</a>
        <?php if ($isAdmin || $isRepartidor): ?>
            <a href="<?= SITE_URL ?>/admin/pedidos.php" class="btn btn-primary">Volver al panel</a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
