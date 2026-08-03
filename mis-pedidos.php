<?php
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
$pdo = getDB();

$s = $pdo->prepare("SELECT p.*, (SELECT COUNT(*) FROM detalle_pedidos WHERE pedido_id=p.id) as items
                    FROM pedidos p WHERE p.usuario_id=? ORDER BY p.creado_en DESC");
$s->execute([$_SESSION['usuario_id']]);
$pedidos = $s->fetchAll();

$pageTitle = 'Mis Pedidos';
include 'includes/header.php';
?>
<div class="container orders-layout">
  <div class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Inicio</a><span>›</span><strong>Mis Pedidos</strong></div>

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
    <h1 class="page-title" style="margin-bottom:0;"><i class="fas fa-box"></i> Mis Pedidos</h1>
    <a href="<?= SITE_URL ?>/productos.php" class="btn-main" style="font-size:13px;padding:10px 20px;"><i class="fas fa-plus"></i> Nuevo pedido</a>
  </div>

  <?php if(empty($pedidos)): ?>
    <div class="no-results" style="background:var(--dark2);border-radius:var(--radius-lg);border:1.5px dashed var(--border);">
      <i class="fas fa-box-open"></i>
      <p style="font-size:18px;font-weight:700;color:var(--white);margin-bottom:8px;">No tienes pedidos aún</p>
      <p>Explora nuestro catálogo y realiza tu primera compra</p>
      <a href="<?= SITE_URL ?>/productos.php" class="btn-main" style="margin-top:20px;display:inline-flex;"><i class="fas fa-shopping-bag"></i> Ir al catálogo</a>
    </div>
  <?php else: ?>
    <?php foreach($pedidos as $pedido): ?>
      <?php
        $ds = $pdo->prepare("SELECT dp.*, p.nombre, p.marca, p.imagen, c.icono FROM detalle_pedidos dp JOIN productos p ON dp.producto_id=p.id JOIN categorias c ON p.categoria_id=c.id WHERE dp.pedido_id=?");
        $ds->execute([$pedido['id']]);
        $detalles = $ds->fetchAll();
        $badgeClass = 'badge-' . strtolower($pedido['estado']);
      ?>
      <div class="order-card">
        <div class="order-card-header">
          <div>
            <div class="order-code">#<?= str_pad($pedido['id'],6,'0',STR_PAD_LEFT) ?></div>
            <div class="order-date"><i class="fas fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($pedido['creado_en'])) ?></div>
          </div>
          <span class="order-badge <?= $badgeClass ?>">
            <?= ucfirst($pedido['estado']) ?>
          </span>
        </div>
        <div class="order-card-body">
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
            <?php foreach($detalles as $det): ?>
              <span class="order-item-chip">
                <?php if (!empty($det['imagen'])): ?>
                  <img src="<?= SITE_URL ?>/<?= sanitize($det['imagen']) ?>" alt="<?= sanitize($det['nombre']) ?>" style="width:40px;height:40px;object-fit:contain;border-radius:6px;">
                <?php else: ?>
                  <i class="fas <?= sanitize($det['icono']) ?>"></i>
                <?php endif; ?>
                <?= sanitize($det['nombre']) ?> x<?= $det['cantidad'] ?>
              </span>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;gap:20px;font-size:12px;color:var(--gray);flex-wrap:wrap;">
            <span><i class="fas fa-truck" style="color:var(--primary);"></i>
              <?= $pedido['tipo_envio'] === 'delivery' ? 'Delivery' : 'Recojo en tienda' ?>
            </span>
            <span><i class="fas fa-credit-card" style="color:var(--primary);"></i>
              <?= ucfirst($pedido['metodo_pago'] ?? 'N/A') ?>
            </span>
            <?php if($pedido['direccion_entrega']): ?>
              <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i>
                <?= sanitize(substr($pedido['direccion_entrega'],0,50)) ?>...
              </span>
            <?php endif; ?>
          </div>
        </div>
        <div class="order-card-footer">
          <div>
            <div style="font-size:12px;color:var(--gray);margin-bottom:2px;"><?= count($detalles) ?> producto(s)</div>
            <div class="order-total"><?= formatPrice($pedido['total']) ?></div>
          </div>
          <a href="<?= SITE_URL ?>/pedido-detalle.php?id=<?= $pedido['id'] ?>"
             style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:var(--primary-light);color:var(--primary);border:1px solid rgba(255,230,0,.3);border-radius:var(--radius);font-size:13px;font-weight:700;transition:all .2s;"
             onmouseover="this.style.background='var(--primary)';this.style.color='var(--black)';"
             onmouseout="this.style.background='var(--primary-light)';this.style.color='var(--primary)';">
            <i class="fas fa-eye"></i> Ver detalle
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<div style="padding-bottom:40px;"></div>
<?php include 'includes/footer.php'; ?>