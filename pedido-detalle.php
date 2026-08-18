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
                        <?php if ($isAdmin): ?>
                        <button type="button" class="btn-danger-large" style="margin-left:12px;" onclick="confirmEliminarPedido(<?= $pedido['id'] ?>)" title="Eliminar pedido">
                            <i class="fas fa-trash-alt" style="color:#fff;font-size:16px;" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
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
                    <div><strong>Tipo entrega:</strong> <?= $pedido['tipo_entrega'] === 'delivery' ? 'Delivery' : ($pedido['tipo_entrega'] === 'provincia' ? 'Provincia' : 'Recojo en tienda') ?></div>
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
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php
            // Calcular subtotal a partir de los detalles (antes de descuento/envío)
            $subtotal_calc = 0;
            foreach ($detalles as $it) { $subtotal_calc += (float)($it['subtotal'] ?? 0); }
            // Determinar descuento: preferir valor guardado en pedido, si no existe calcular diferencia
            $discount_shown = 0.0;
            if (!empty($pedido['descuento']) && (float)$pedido['descuento'] > 0) {
                $discount_shown = (float)$pedido['descuento'];
            } else {
                // Fallback: si el subtotal calculado es mayor que el total, la diferencia es el descuento
                $diff = $subtotal_calc - (float)$pedido['total'];
                $discount_shown = $diff > 0 ? round($diff, 2) : 0.0;
            }

            // Determinar nota del descuento: porcentaje o monto según el cupón
            $discount_note = '';
            if (!empty($pedido['cupon_codigo'])) {
                try {
                    $stmtCup = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? LIMIT 1");
                    $stmtCup->execute([$pedido['cupon_codigo']]);
                    $cup = $stmtCup->fetch(PDO::FETCH_ASSOC);
                    if (!$cup) {
                        $stmtCup = $pdo->prepare("SELECT c.* FROM usuario_cupones uc JOIN cupones c ON uc.cupon_id=c.id WHERE uc.codigo_personal = ? LIMIT 1");
                        $stmtCup->execute([$pedido['cupon_codigo']]);
                        $cup = $stmtCup->fetch(PDO::FETCH_ASSOC);
                    }
                    if ($cup) {
                        if (($cup['tipo_descuento'] ?? 'porcentaje') === 'monto') {
                            $discount_note = 'S/ ' . number_format((float)$cup['descuento'], 2);
                        } else {
                            $p = rtrim(rtrim(number_format((float)$cup['descuento'], 2), '0'), '.');
                            $discount_note = $p . '%';
                        }
                    }
                } catch (Exception $e) {
                    // ignore DB lookup failures and fallback to computed percent
                }
            }
            if ($discount_note === '' && $discount_shown > 0) {
                // Fallback: mostrar el monto en soles cuando no se determinó el tipo
                $discount_note = 'S/ ' . number_format($discount_shown, 2);
            }
        ?>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;margin-top:18px;">
            
            <div style="width:100%;max-width:360px;">
                
                <?php if ($discount_shown > 0): ?>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;border-radius:12px;background:rgba(236,253,245,0.8);border:1px solid rgba(134,239,172,0.6);margin-bottom:6px;">
                    <span style="color:#166534;">Descuento <?= !empty($pedido['cupon_codigo']) ? '('.sanitize($pedido['cupon_codigo']).')' : '' ?> <?= $discount_note ? '<span style="font-size:12px;color:#166534;margin-left:8px;">('.sanitize($discount_note).')</span>' : '' ?></span>
                    <strong style="color:#166534;">-<?= formatPrice($discount_shown) ?></strong>
                </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;padding:12px 16px;border-radius:12px;background:rgba(59,130,246,.08);font-weight:700;color:var(--gris1);">
                    <span>Total</span>
                    <span><?= formatPrice($pedido['total']) ?></span>
                </div>
            </div>
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

<script>
// Styles for prominent delete button
(function(){
    const css = `
    .btn-danger-large{background:linear-gradient(180deg,#f44336,#c62828);border:1px solid #b71c1c;color:#fff;padding:8px;width:44px;height:40px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(196,51,51,0.25);transition:transform .12s,box-shadow .12s;}
    .btn-danger-large:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(196,51,51,0.32);} 
    `;
    const s = document.createElement('style'); s.appendChild(document.createTextNode(css)); document.head.appendChild(s);
})();
function confirmEliminarPedido(id) {
    if (!confirm('¿Eliminar este pedido de forma permanente? Esta acción no se puede deshacer.')) return;
    fetch('<?= SITE_URL ?>/admin/eliminar-pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'pedido_id=' + encodeURIComponent(id)
    }).then(r => r.json()).then(d => {
        if (d && d.success) {
            alert('Pedido eliminado. Redirigiendo al panel.');
            window.location.href = '<?= SITE_URL ?>/admin/pedidos.php';
        } else {
            alert('No se pudo eliminar el pedido: ' + (d && d.message ? d.message : 'Error'));
        }
    }).catch(err => { console.error(err); alert('Error de red.'); });
}
</script>
