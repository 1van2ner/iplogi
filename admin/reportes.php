<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Reportes';
$pdo = getDB();

// Ingresos por mes (últimos 6 meses)
$ingresosMes = $pdo->query("
    SELECT DATE_FORMAT(creado_en,'%Y-%m') as mes,
           DATE_FORMAT(creado_en,'%b %Y') as mes_label,
           COUNT(*) as n_pedidos,
           SUM(total) as total_ingresos
    FROM pedidos
    WHERE estado != 'cancelado'
    AND creado_en >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(creado_en,'%Y-%m')
    ORDER BY mes
")->fetchAll();

// Ventas por categoría
$ventasCat = $pdo->query("
    SELECT c.nombre, c.icono, COUNT(dp.id) as n_ventas, SUM(dp.subtotal) as total
    FROM detalle_pedidos dp
    JOIN productos p ON dp.producto_id=p.id 
    JOIN categorias c ON p.categoria_id=c.id
    JOIN pedidos ped ON dp.pedido_id=ped.id
    WHERE ped.estado != 'cancelado'
    GROUP BY c.id ORDER BY total DESC
")->fetchAll();

// Top productos
$topProductos = $pdo->query("
    SELECT p.nombre, p.marca, cat.icono, SUM(dp.cantidad) as total_vendido, SUM(dp.subtotal) as ingresos
    FROM detalle_pedidos dp
    JOIN productos p ON dp.producto_id=p.id
    JOIN categorias cat ON p.categoria_id=cat.id
    JOIN pedidos ped ON dp.pedido_id=ped.id
    WHERE ped.estado != 'cancelado'
    GROUP BY p.id ORDER BY total_vendido DESC LIMIT 10
")->fetchAll();

// Métodos de pago
$metodosPago = $pdo->query("
    SELECT metodo_pago, COUNT(*) as n, SUM(total) as total
    FROM pedidos WHERE estado != 'cancelado'
    GROUP BY metodo_pago ORDER BY n DESC
")->fetchAll();

// Tipo entrega
$tipoEntrega = $pdo->query("
    SELECT tipo_entrega, COUNT(*) as n FROM pedidos GROUP BY tipo_entrega
")->fetchAll();

$totalGeneral   = $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado!='cancelado'")->fetchColumn();
$totalPedidos   = $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$totalClientes  = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol='cliente'")->fetchColumn();
$ticketPromedio = $totalPedidos > 0 ? $totalGeneral / $totalPedidos : 0;

include '../includes/header.php';
?>

<div class="container" style="padding:24px 20px;">
    <div style="margin-bottom:24px;">
        <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-chart-bar" style="color:var(--primary);"></i> Reportes y Estadísticas</h1>
        <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Reportes</div>
    </div>

    <!-- KPIs -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;">
        <?php $kpis = [
            ['fa-soles-sign','Ingresos Totales','S/ '.number_format($totalGeneral,2),'#f59e0b'],
            ['fa-shopping-bag','Total Pedidos',$totalPedidos,'#3b82f6'],
            ['fa-users','Clientes Registrados',$totalClientes,'#22c55e'],
            ['fa-chart-line','Ticket Promedio','S/ '.number_format($ticketPromedio,2),'#8b5cf6'],
        ];
        foreach ($kpis as [$ico,$lbl,$val,$col]): ?>
        <div style="background:white;border-radius:var(--radius-lg);padding:20px;border:1px solid var(--border);box-shadow:var(--shadow);">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:46px;height:46px;background:<?= $col ?>20;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:<?= $col ?>;">
                    <i class="fas <?= $ico ?>"></i>
                </div>
                <div>
                    <div style="font-size:20px;font-weight:800;"><?= $val ?></div>
                    <div style="font-size:12px;color:var(--gray);"><?= $lbl ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

        <!-- INGRESOS POR MES -->
        <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fas fa-calendar-alt" style="color:var(--primary);"></i> Ingresos últimos 6 meses</h3>
            <?php if (empty($ingresosMes)): ?>
                <p style="text-align:center;color:var(--gray);padding:20px;">Sin datos aún</p>
            <?php else:
                $maxVal = max(array_column($ingresosMes,'total_ingresos')) ?: 1;
                foreach ($ingresosMes as $m):
                    $pct = round(($m['total_ingresos']/$maxVal)*100);
            ?>
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                        <span style="font-weight:600;"><?= $m['mes_label'] ?></span>
                        <span style="color:var(--primary);font-weight:700;"><?= formatPrice($m['total_ingresos']) ?> <span style="color:var(--gray);font-weight:400;">(<?= $m['n_pedidos'] ?> pedidos)</span></span>
                    </div>
                    <div style="background:var(--light);border-radius:4px;height:10px;">
                        <div style="background:var(--primary);height:10px;border-radius:4px;width:<?= $pct ?>%;transition:width 0.3s;"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- VENTAS POR CATEGORÍA -->
        <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><i class="fas fa-th-large" style="color:var(--primary);"></i> Ventas por Categoría</h3>
            <?php if (empty($ventasCat)): ?>
                <p style="text-align:center;color:var(--gray);padding:20px;">Sin datos aún</p>
            <?php else:
                $maxVC = max(array_column($ventasCat,'total')) ?: 1;
                foreach ($ventasCat as $vc):
                    $pctVC = round(($vc['total']/$maxVC)*100);
            ?>
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
                        <span style="font-weight:600;display:flex;align-items:center;gap:6px;">
                            <i class="fas <?= $vc['icono'] ?>" style="color:var(--primary);width:16px;"></i>
                            <?= sanitize($vc['nombre']) ?>
                        </span>
                        <span style="color:var(--primary);font-weight:700;"><?= formatPrice($vc['total']) ?></span>
                    </div>
                    <div style="background:var(--light);border-radius:4px;height:8px;">
                        <div style="background:var(--secondary);height:8px;border-radius:4px;width:<?= $pctVC ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

        <!-- TOP PRODUCTOS -->
        <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--border);font-size:16px;font-weight:700;">
                <i class="fas fa-trophy" style="color:#f59e0b;"></i> Top 10 Productos más Vendidos
            </div>
            <?php if (empty($topProductos)): ?>
                <p style="text-align:center;color:var(--gray);padding:30px;">Sin ventas registradas</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="padding:8px 14px;text-align:left;font-weight:700;color:var(--gray);">#</th>
                        <th style="padding:8px 14px;text-align:left;font-weight:700;color:var(--gray);">Producto</th>
                        <th style="padding:8px 14px;text-align:center;font-weight:700;color:var(--gray);">Vendidos</th>
                        <th style="padding:8px 14px;text-align:right;font-weight:700;color:var(--gray);">Ingresos</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProductos as $idx => $tp): ?>
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:8px 14px;font-weight:800;color:<?= $idx===0?'#f59e0b':($idx===1?'#94a3b8':($idx===2?'#b45309':'var(--gray)')) ?>;">
                            <?= $idx < 3 ? '<i class="fas fa-medal"></i>' : ($idx+1) ?>
                        </td>
                        <td style="padding:8px 14px;">
                            <div style="font-weight:600;"><?= sanitize($tp['nombre']) ?></div>
                            <div style="font-size:11px;color:var(--primary);"><?= sanitize($tp['marca']) ?></div>
                        </td>
                        <td style="padding:8px 14px;text-align:center;font-weight:700;"><?= $tp['total_vendido'] ?></td>
                        <td style="padding:8px 14px;text-align:right;font-weight:700;color:var(--primary);"><?= formatPrice($tp['ingresos']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- MÉTODOS DE PAGO + TIPO ENTREGA -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);padding:20px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;"><i class="fas fa-credit-card" style="color:var(--primary);"></i> Métodos de Pago</h3>
                <?php $pagoLabels=['tarjeta_credito'=>'Tarjeta Crédito','tarjeta_debito'=>'Tarjeta Débito','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia','efectivo_contra_entrega'=>'Efectivo'];
                $totalMP = array_sum(array_column($metodosPago,'n')) ?: 1;
                foreach ($metodosPago as $mp):
                    $pct = round(($mp['n']/$totalMP)*100);
                ?>
                <div style="margin-bottom:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                        <span><?= $pagoLabels[$mp['metodo_pago']] ?? $mp['metodo_pago'] ?></span>
                        <span style="font-weight:700;"><?= $mp['n'] ?> (<?= $pct ?>%)</span>
                    </div>
                    <div style="background:var(--light);border-radius:4px;height:7px;">
                        <div style="background:var(--primary);height:7px;border-radius:4px;width:<?= $pct ?>%;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);padding:20px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;"><i class="fas fa-truck" style="color:var(--primary);"></i> Tipo de Entrega</h3>
                <?php $entregaLabels=['delivery'=>'Delivery','recojo_tienda'=>'Recojo en tienda'];
                $totalTE = array_sum(array_column($tipoEntrega,'n')) ?: 1;
                foreach ($tipoEntrega as $te):
                    $pct = round(($te['n']/$totalTE)*100);
                ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div>
                        <div style="font-size:13px;font-weight:700;"><?= $entregaLabels[$te['tipo_entrega']] ?? $te['tipo_entrega'] ?></div>
                        <div style="font-size:22px;font-weight:800;color:var(--primary);"><?= $pct ?>%</div>
                    </div>
                    <div style="font-size:24px;color:#e2e8f0;">
                        <i class="fas <?= $te['tipo_entrega']==='delivery'?'fa-truck':'fa-store' ?>"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>