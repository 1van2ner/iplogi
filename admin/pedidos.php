<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Gestión de Pedidos';
$pdo = getDB();

// Actualizar estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'])) {
    $pid    = (int)$_POST['pedido_id'];
    $estado = $_POST['estado'] ?? '';
    $allowed = ['pendiente','confirmado','procesando','enviado','entregado','cancelado'];
    if (in_array($estado, $allowed)) {
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
        $estadoColors = ['pendiente'=>['#fef3c7','#92400e'],'confirmado'=>['#dbeafe','#1e40af'],'procesando'=>['#ede9fe','#5b21b6'],'enviado'=>['#d1fae5','#065f46'],'entregado'=>['#dcfce7','#166534'],'cancelado'=>['#fee2e2','#991b1b']];
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
    <form method="GET" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <?php if ($estado): ?><input type="hidden" name="estado" value="<?= $estado ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= $q ?>" placeholder="Buscar por código, nombre, email..." style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:14px;flex:1;min-width:220px;">
        <select name="entrega" style="padding:8px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:14px;">
            <option value="">Tipo de entrega</option>
            <option value="delivery" <?= $entrega==='delivery'?'selected':'' ?>>Delivery</option>
            <option value="recojo_tienda" <?= $entrega==='recojo_tienda'?'selected':'' ?>>Recojo en tienda</option>
        </select>
        <button type="submit" style="padding:8px 18px;background:var(--primary);color:white;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:600;">Filtrar</button>
    </form>

    <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);overflow:hidden;">
        <div style="padding:12px 20px;background:var(--light);border-bottom:1px solid var(--border);font-size:13px;color:var(--gray);">
            <strong><?= $total ?></strong> pedidos encontrados
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Código</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Cliente</th>
                        <th style="padding:10px 14px;text-align:center;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Items</th>
                        <th style="padding:10px 14px;text-align:right;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Total</th>
                        <th style="padding:10px 14px;text-align:center;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Entrega</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Pago</th>
                        <th style="padding:10px 14px;text-align:center;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Estado</th>
                        <th style="padding:10px 14px;text-align:left;font-weight:700;color:var(--gray);border-bottom:1px solid var(--border);">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $p):
                        $est = $estadoColors[$p['estado']] ?? ['#f1f5f9','#64748b'];
                    ?>
                    <tr style="border-bottom:1px solid var(--border);" onmouseover="this.style.background='#fafafa'" onmouseout="this.style.background=''">
                        <td style="padding:10px 14px;">
                            <a href="<?= SITE_URL ?>/pedido-detalle.php?id=<?= $p['id'] ?>" target="_blank" style="font-weight:700;color:var(--primary);text-decoration:none;"><?= sanitize($p['codigo']) ?></a>
                        </td>
                        <td style="padding:10px 14px;">
                            <div style="font-weight:600;"><?= sanitize($p['nombre'].' '.$p['apellido']) ?></div>
                            <div style="font-size:11px;color:var(--gray);"><?= sanitize($p['email']) ?></div>
                            <?php if ($p['telefono']): ?><div style="font-size:11px;color:var(--gray);"><?= sanitize($p['telefono']) ?></div><?php endif; ?>
                        </td>
                        <td style="padding:10px 14px;text-align:center;font-weight:600;"><?= $p['n_items'] ?></td>
                        <td style="padding:10px 14px;text-align:right;font-weight:800;color:var(--primary);"><?= formatPrice($p['total']) ?></td>
                        <td style="padding:10px 14px;text-align:center;">
                            <?php if ($p['tipo_entrega']==='delivery'): ?>
                                <span style="font-size:11px;background:#eff6ff;color:var(--primary);padding:2px 8px;border-radius:10px;font-weight:700;"><i class="fas fa-truck"></i> Delivery</span>
                            <?php else: ?>
                                <span style="font-size:11px;background:#f0fdf4;color:var(--success);padding:2px 8px;border-radius:10px;font-weight:700;"><i class="fas fa-store"></i> Tienda</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px 14px;font-size:12px;color:var(--gray);">
                            <?php $pagoLabels = ['tarjeta_credito'=>'T. Crédito','tarjeta_debito'=>'T. Débito','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transf.','efectivo_contra_entrega'=>'Efectivo'];
                            echo $pagoLabels[$p['metodo_pago']] ?? $p['metodo_pago']; ?>
                        </td>
                        <td style="padding:10px 14px;text-align:center;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="<?= $q?"q=$q&":"" ?><?= $estado?"estado=$estado&":"" ?>pag=<?= $pag ?>" value="">
                                <select name="estado" onchange="this.form.submit()"
                                        style="border:1.5px solid <?= $est[1] ?>;background:<?= $est[0] ?>;color:<?= $est[1] ?>;border-radius:12px;padding:3px 8px;font-size:11px;font-weight:700;cursor:pointer;">
                                    <?php foreach (['pendiente','confirmado','procesando','enviado','entregado','cancelado'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $p['estado']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td style="padding:10px 14px;font-size:12px;color:var(--gray);">
                            <?= date('d/m/y', strtotime($p['creado_en'])) ?><br>
                            <?= date('H:i', strtotime($p['creado_en'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPags > 1): ?>
        <div style="padding:14px 20px;display:flex;justify-content:center;gap:6px;border-top:1px solid var(--border);">
            <?php for ($i=1;$i<=$totalPags;$i++): ?>
                <a href="?pag=<?= $i ?><?= $q?"&q=".urlencode($q):"" ?><?= $estado?"&estado=$estado":"" ?><?= $entrega?"&entrega=$entrega":"" ?>"
                   style="width:34px;height:34px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;border:1.5px solid <?= $i==$pag?'var(--primary)':'var(--border)' ?>;background:<?= $i==$pag?'var(--primary)':'white' ?>;color:<?= $i==$pag?'white':'var(--dark)' ?>;text-decoration:none;">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>