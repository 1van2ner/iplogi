<?php
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pedidoId = (int)($_GET['id'] ?? 0);
if (!$pedidoId) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pdo = getDB();
$s = $pdo->prepare("SELECT * FROM pedidos WHERE id=? AND usuario_id=?"); $s->execute([$pedidoId,$_SESSION['usuario_id']]);
$pedido = $s->fetch();
if (!$pedido) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$ds = $pdo->prepare("SELECT dp.*,p.nombre,p.marca,p.imagen,c.icono FROM detalle_pedidos dp JOIN productos p ON dp.producto_id=p.id JOIN categorias c ON p.categoria_id=c.id WHERE dp.pedido_id=?");
$ds->execute([$pedidoId]); $detalles = $ds->fetchAll();
$pageTitle = 'Pedido Confirmado';
include 'includes/header.php';
?>
<div class="container">
  <div class="order-success">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <h1>¡Pedido confirmado!</h1>
    <p>Gracias por tu compra. Hemos recibido tu pedido correctamente.</p>
    <div class="order-code-display">#<?= str_pad($pedido['id'],6,'0',STR_PAD_LEFT) ?></div>
    <p style="color:var(--gray);font-size:14px;">
      <i class="fas fa-envelope" style="color:var(--primary);"></i>
      Recibirás confirmación en <strong style="color:var(--white);"><?= sanitize($_SESSION['email']) ?></strong>
    </p>

    <div class="order-detail-box">
      <div class="order-detail-row"><span>Estado</span><span style="color:var(--warning);">Pendiente de confirmación</span></div>
      <div class="order-detail-row"><span>Tipo de entrega</span><span><?= ($pedido['tipo_entrega'] ?? '') === 'delivery' ? 'Delivery a domicilio' : (($pedido['tipo_entrega'] ?? '') === 'provincia' ? 'Provincia' : 'Recojo en tienda') ?></span></div>
      <div class="order-detail-row"><span>Método de pago</span><span><?= ucfirst($pedido['metodo_pago'] ?? 'N/A') ?></span></div>
      <div class="order-detail-row"><span>Productos (<?= count($detalles) ?>)</span>
        <span><?php foreach($detalles as $d): ?><?= sanitize($d['nombre']) ?><?= !$d===end($detalles)?', ':'' ?><?php endforeach; ?></span>
      </div>
      <div class="order-detail-row"><span>Estados de línea</span>
        <span>
          <?php foreach($detalles as $d): ?>
            <?= sanitize($d['nombre']) ?>: <strong><?= sanitize($d['estado'] ?? 'pendiente') ?></strong><?= next($detalles) ? ', ' : '' ?>
          <?php endforeach; ?>
        </span>
      </div>
      <div class="order-detail-row"><span>TOTAL</span><span><?= formatPrice($pedido['total']) ?></span></div>
    </div>

    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:10px;">
      <a href="<?= SITE_URL ?>/mis-pedidos.php" class="btn-main"><i class="fas fa-box"></i> Ver mis pedidos</a>
      <a href="<?= SITE_URL ?>/productos.php" class="btn-sec"><i class="fas fa-shopping-bag"></i> Seguir comprando</a>
    </div>
    <div style="margin-top:20px;">
      <a href="https://wa.me/51987654321?text=Hola, realicé el pedido #<?= str_pad($pedido['id'],6,'0',STR_PAD_LEFT) ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:8px;color:var(--primary);font-size:14px;font-weight:600;">
        <i class="fab fa-whatsapp" style="font-size:20px;"></i> Consultar por WhatsApp
      </a>
    </div>
  </div>
</div>
<?php include 'includes/footer.php'; ?>