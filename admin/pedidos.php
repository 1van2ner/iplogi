<?php
require_once '../includes/config.php';
requireLogin();
// Permitir acceso a administradores y al rol de reparto (repartidor)
if (!isAdmin() && ($_SESSION['rol'] ?? '') !== 'repartidor') { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Gestión de Pedidos';
$pdo = getDB();

// Actualizar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $pid    = (int)$_POST['pedido_id'];
    $estado = $_POST['estado'] ?? '';
    $tipoEntregaStmt = $pdo->prepare("SELECT tipo_entrega FROM pedidos WHERE id = ?");
    $tipoEntregaStmt->execute([$pid]);
    $tipoEntrega = $tipoEntregaStmt->fetchColumn();

    if ($tipoEntrega === 'provincia') {
        $allowed = ['pendiente','almacen','enviado','cancelado'];
    } else {
        $allowed = ['pendiente','procesando','enviado','entregado','cancelado'];
    }

    if (in_array($estado, $allowed, true)) {
        $pdo->prepare("UPDATE pedidos SET estado=? WHERE id=?")->execute([$estado,$pid]);
    }
}

$q       = sanitize($_GET['q'] ?? '');
$estado  = sanitize($_GET['estado'] ?? '');
$entrega = sanitize($_GET['entrega'] ?? '');
$pag     = max(1,(int)($_GET['pag'] ?? 1));
$por     = 15;
$off     = ($pag-1)*$por;

$where  = [];
$params = [];
if ($q)       { $where[] = '(p.codigo LIKE ? OR u.nombre LIKE ? OR u.email LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($estado)  { $where[] = 'p.estado=?';        $params[] = $estado; }
if ($entrega) { $where[] = 'p.tipo_entrega=?';  $params[] = $entrega; }
$ws = $where ? 'WHERE '.implode(' AND ',$where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM pedidos p JOIN usuarios u ON p.usuario_id=u.id $ws");
$total->execute($params);
$total = (int)$total->fetchColumn();
$totalPags = ceil($total/$por);

$stmt = $pdo->prepare("
    SELECT p.*, u.nombre, u.apellido, u.email, u.telefono,
           (SELECT COUNT(*) FROM detalle_pedidos WHERE pedido_id=p.id) as n_items
    FROM pedidos p JOIN usuarios u ON p.usuario_id=u.id
    $ws ORDER BY p.creado_en DESC LIMIT $por OFFSET $off
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Stats rápidas
$stats = $pdo->query("SELECT estado, COUNT(*) as n FROM pedidos GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
$isAdmin = isAdmin();

include '../includes/header.php';
?>

<div class="container" style="padding:24px 20px;">
    <div style="margin-bottom:20px;">
        <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-shopping-bag" style="color:var(--primary);"></i> Gestión de Pedidos</h1>
        <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Pedidos</div>
    </div>

    <!-- BADGES ESTADO -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
        <?php
        $estadoColors = ['pendiente'=>['#fef3c7','#92400e'],'almacen'=>['#dbeafe','#1e40af'],'enviado'=>['#d1fae5','#065f46']];
        foreach ($estadoColors as $est=>[$bg,$color]):
        ?>
        <a href="pedidos.php?estado=<?= $est ?>" style="padding:6px 14px;border-radius:20px;background:<?= $bg ?>;color:<?= $color ?>;font-size:12px;font-weight:700;text-decoration:none;border:1.5px solid <?= $estado===$est?$color:'transparent' ?>;">
            <?= ucfirst($est) ?>: <?= $stats[$est] ?? 0 ?>
        </a>
        <?php endforeach; ?>
        <?php if ($estado): ?>
            <a href="pedidos.php" style="padding:6px 14px;border-radius:20px;background:#f1f5f9;color:var(--gray);font-size:12px;font-weight:700;text-decoration:none;">✕ Quitar filtro</a>
        <?php endif; ?>
    </div>

    <!-- FILTROS -->
    <form method="GET" class="admin-orders-filters">
        <?php if ($estado): ?><input type="hidden" name="estado" value="<?= $estado ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= $q ?>" placeholder="Buscar por código, nombre, email..." class="input-search">
        <select name="entrega" class="input-select">
            <option value="">Tipo de entrega</option>
            <option value="delivery" <?= $entrega==='delivery'?'selected':'' ?>>Delivery</option>
            <option value="provincia" <?= $entrega==='provincia'?'selected':'' ?>>Provincia</option>
            <option value="recojo_tienda" <?= $entrega==='recojo_tienda'?'selected':'' ?>>Recojo en tienda</option>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <?php if ($estado): ?><a href="pedidos.php" class="btn btn-secondary">Quitar filtro</a><?php endif; ?>
    </form>

    <div class="admin-orders">
        <div class="admin-orders-top">
            <div>
                <h2>Gestión de Pedidos</h2>
                <p class="admin-orders-subtitle">Monitorea y actualiza el estado de los pedidos desde el panel administrativo.</p>
            </div>
            <div class="admin-orders-summary"><strong><?= $total ?></strong> pedidos encontrados</div>
        </div>

        <div class="admin-orders-table-wrap">
            <table class="atbl">
                <thead>
                    <tr>
                        <th class="cell-code">Código</th>
                        <th class="cell-left">Cliente</th>
                        <th class="cell-center">Items</th>
                        <th class="cell-right">Total</th>
                        <th class="cell-center">Entrega</th>
                        <th class="cell-left">Pago</th>
                        <th class="cell-center">Estado</th>
                        <th class="cell-left">Fecha</th>
                        <?php if ($isAdmin): ?>
                        <th class="cell-center">Acción</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $pagoLabels = ['tarjeta_credito'=>'T. Crédito','tarjeta_debito'=>'T. Débito','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transf.','efectivo_contra_entrega'=>'Efectivo'];
                    foreach ($pedidos as $p):
                        $est = $estadoColors[$p['estado']] ?? ['#f1f5f9','#64748b'];
                        $codigoMostrar = isset($p['codigo']) && $p['codigo'] ? sanitize($p['codigo']) : str_pad($p['id'],6,'0',STR_PAD_LEFT);
                    ?>
                    <tr>
                        <td class="cell-code"><a href="<?= SITE_URL ?>/pedido-detalle.php?id=<?= $p['id'] ?>" class="order-code">#<?= $codigoMostrar ?></a></td>
                        <td class="cell-left customer-cell">
                            <div class="customer-name"><?= sanitize($p['nombre'].' '.$p['apellido']) ?></div>
                            <div class="customer-meta"><?= sanitize($p['email']) ?></div>
                            <?php if ($p['telefono']): ?><div class="customer-meta"><?= sanitize($p['telefono']) ?></div><?php endif; ?>
                        </td>
                        <td class="cell-center"><strong><?= $p['n_items'] ?></strong></td>
                        <td class="cell-right order-total"><?= formatPrice($p['total']) ?></td>
                        <td class="cell-center">
                            <?php if ($p['tipo_entrega'] === 'delivery'): ?>
                                <span class="badge badge-delivery"><i class="fas fa-truck"></i> Delivery</span>
                            <?php elseif ($p['tipo_entrega'] === 'provincia'): ?>
                                <span class="badge badge-province badge-province-img"><img src="<?= SITE_URL ?>/assets/img/provincia.svg" alt="Provincia" aria-hidden="true"> Provincia</span>
                            <?php else: ?>
                                <span class="badge badge-store"><i class="fas fa-store"></i> Tienda</span>
                            <?php endif; ?>
                        </td>
                        <td class="cell-left payment-cell"><?= $pagoLabels[$p['metodo_pago']] ?? $p['metodo_pago'] ?></td>
                        <td class="cell-center status-cell">
                            <form method="POST" class="status-form">
                                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                <?php if ($q): ?><input type="hidden" name="q" value="<?= $q ?>"><?php endif; ?>
                                <?php if ($estado): ?><input type="hidden" name="estado" value="<?= $estado ?>"><?php endif; ?>
                                <?php if ($entrega): ?><input type="hidden" name="entrega" value="<?= $entrega ?>"><?php endif; ?>
                                <input type="hidden" name="pag" value="<?= $pag ?>">
                                <?php
                                    $estadoOptions = $p['tipo_entrega'] === 'provincia'
                                        ? ['pendiente','almacen','enviado']
                                        : ['pendiente','procesando','enviado','entregado','cancelado'];
                                    if (!in_array($p['estado'], $estadoOptions, true)) {
                                        $estadoOptions[] = $p['estado'];
                                    }
                                ?>
                                <select name="estado" class="status-select" onchange="this.form.submit()">
                                    <?php foreach ($estadoOptions as $s): ?>
                                        <option value="<?= $s ?>" <?= $p['estado'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td class="cell-left date-cell">
                            <div><?= date('d/m/y', strtotime($p['creado_en'])) ?></div>
                            <div class="customer-meta"><?= date('H:i', strtotime($p['creado_en'])) ?></div>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="cell-center action-cell"><a href="<?= SITE_URL ?>/pedido-detalle.php?id=<?= $p['id'] ?>" class="btn btn-edit">Ver / Editar</a></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPags > 1): ?>
        <div class="pagination">
            <?php for ($i=1;$i<=$totalPags;$i++): ?>
                <a href="?pag=<?= $i ?><?= $q ? '&q=' . urlencode($q) : '' ?><?= $estado ? '&estado=' . $estado : '' ?><?= $entrega ? '&entrega=' . $entrega : '' ?>" class="page-link <?= $i === $pag ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php include '../includes/footer.php'; ?>