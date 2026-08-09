<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$pageTitle = 'Eventos';
$pdo = getDB();

$stmt = $pdo->query("SELECT COUNT(*) FROM pedidos");
$totalPedidos = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1");
$totalProductos = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin'");
$totalUsuarios = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='pendiente'");
$pedidosPend = (int)$stmt->fetchColumn();

$s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
$s->execute([$_SESSION['usuario_id']]);
$adminUser = $s->fetch();

include '../includes/header.php';
?>

<style>
:root { --amarillo-texto:#6b7300; }
.admin-layout{display:grid;grid-template-columns:260px 1fr;gap:24px;padding:28px 0 60px;align-items:start;}
.admin-layout > main{min-width:0;}
.admin-sidebar{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);padding:24px;position:sticky;top:150px;}
.admin-avatar{width:80px;height:80px;background:var(--amarillo);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:34px;color:#000;margin:0 auto 14px;box-shadow:0 0 0 4px rgba(237,232,42,.25);}
.admin-name{text-align:center;font-size:17px;font-weight:900;color:var(--blanco);margin-bottom:4px;}
.admin-role{text-align:center;margin-bottom:6px;}
.admin-email{text-align:center;font-size:12px;color:var(--gris3);margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--borde);}
.admin-stat-mini{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:16px 0;}
.mini-stat{background:var(--bg3);border-radius:8px;padding:10px;text-align:center;}
.mini-stat .v{font-size:18px;font-weight:900;color:var(--amarillo-texto);}
.mini-stat .l{font-size:10px;color:var(--gris3);margin-top:1px;}
.admin-nav a{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:var(--r);font-size:13px;color:var(--gris2);transition:all .2s;margin-bottom:3px;text-decoration:none;font-weight:600;}
.admin-nav a:hover{background:rgba(237,232,42,.1);color:var(--amarillo-texto);}
.admin-nav a.active{background:rgba(237,232,42,.15);color:var(--amarillo-texto);border-left:3px solid var(--amarillo);padding-left:9px;}
.admin-nav a i{width:18px;color:var(--amarillo-texto);font-size:14px;}
.admin-nav .sep{height:1px;background:var(--borde);margin:10px 0;}
.dash-panel{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);overflow:hidden;margin-bottom:20px;}
.dash-panel-head{display:flex;justify-content:space-between;align-items:center;padding:13px 18px;background:var(--bg3);border-bottom:1px solid var(--borde);}
.dash-panel-head h3{font-size:14px;font-weight:800;color:var(--blanco);margin:0;display:flex;align-items:center;gap:7px;}
.dash-panel-head a{font-size:12px;color:var(--amarillo-texto);text-decoration:none;font-weight:700;}
.admin-main-title{font-size:22px;font-weight:900;color:var(--blanco);margin:0;}
</style>

<div class="container">
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
    <div class="admin-name"><?= sanitize($adminUser['nombre'].' '.($adminUser['apellido']??'')) ?></div>
    <div class="admin-role">
      <span style="background:var(--amarillo);color:#000;font-size:10px;font-weight:800;padding:3px 10px;border-radius:12px;text-transform:uppercase;">Administrador</span>
    </div>
    <div class="admin-email"><?= sanitize($adminUser['email']) ?></div>

    <div class="admin-stat-mini">
      <div class="mini-stat"><div class="v"><?= $totalPedidos ?></div><div class="l">Pedidos</div></div>
      <div class="mini-stat"><div class="v"><?= $totalProductos ?></div><div class="l">Productos</div></div>
      <div class="mini-stat"><div class="v"><?= $totalUsuarios ?></div><div class="l">Clientes</div></div>
      <div class="mini-stat"><div class="v" style="color:<?= $pedidosPend>0?'#ffb74d':'var(--amarillo-texto)' ?>;">
        <?= $pedidosPend ?></div><div class="l">Pendientes</div></div>
    </div>

    <nav class="admin-nav">
      <a href="index.php?tab=dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="index.php?tab=productos"><i class="fas fa-box"></i> Productos</a>
      <a href="index.php?tab=usuarios"><i class="fas fa-users"></i> Usuarios</a>
      <a href="promociones.php"><i class="fas fa-fire"></i> Promociones del Mes</a>
      <a href="cupon.php"><i class="fas fa-ticket"></i> Cupones</a>
      <a href="eventos.php" class="active"><i class="fas fa-calendar-day"></i> Eventos</a>
      <a href="index.php?tab=pedidos"><i class="fas fa-shopping-bag"></i> Pedidos</a>
      <div class="sep"></div>
      <a href="categorias.php"><i class="fas fa-th-large"></i> Categorías</a>
      <a href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
      <a href="banners.php"><i class="fas fa-images"></i> Banners</a>
      <a href="testimonios.php"><i class="fas fa-star"></i> Testimonios</a>
      <a href="camwifi.php"><i class="fas fa-camera"></i> Cámaras WiFi</a>
      <a href="proximamente.php"><i class="fas fa-clock"></i> Próximamente</a>
      <div class="sep"></div>
      <a href="<?= SITE_URL ?>/perfil.php"><i class="fas fa-user-edit"></i> Mi Perfil</a>
      <a href="<?= SITE_URL ?>/logout.php" style="color:var(--rojo);"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
    </nav>
  </aside>

  <main>
    <style>
      .event-switch .switch-shell { position: relative; display: inline-block; width: 48px; height: 24px; }
      .event-switch .switch-shell input { position: absolute; opacity: 0; inset: 0; z-index: 2; cursor: pointer; margin: 0; }
      .event-switch .switch-track { position: absolute; inset: 0; border-radius: 999px; background: #555; border: 1px solid var(--borde); transition: all .2s ease; }
      .event-switch .switch-thumb { position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #ccc; transition: all .2s ease; }
      .event-switch .event-switch-input:checked ~ .switch-track { background: var(--amarillo); border-color: var(--amarillo); }
      .event-switch .event-switch-input:checked ~ .switch-thumb { transform: translateX(24px); background: #1b1b1b; }
      .event-switch .event-switch-input:checked ~ .switch-track { box-shadow: inset 0 0 0 2px rgba(255,255,255,.2); }
    </style>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
      <div>
        <h1 class="admin-main-title"><i class="fas fa-calendar-day" style="color:var(--amarillo-texto);margin-right:8px;"></i>Eventos</h1>
        <p style="color:var(--gris3);font-size:13px;margin-top:3px;">Panel para campañas y actividades especiales</p>
      </div>
      <a href="Black_friday.php" style="padding:9px 18px;background:var(--amarillo);color:#000;border-radius:var(--r);font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> Crear evento
      </a>
    </div>

    <div class="dash-panel">
      <div class="dash-panel-head">
        <h3><i class="fas fa-calendar-check" style="color:var(--amarillo-texto);"></i> Eventos programados</h3>
      </div>
      <div style="padding:28px;">
        <div class="event-status-row" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
          <div>
            <div style="font-size:15px;font-weight:700;color:var(--blanco);">Black Friday</div>
            <div style="font-size:12px;margin-top:4px;color:var(--gris3);">Visibilidad del botón principal</div>
          </div>
          <label class="event-switch" style="display:inline-flex;align-items:center;gap:12px;cursor:pointer;">
            <span class="switch-off" style="font-size:12px;color:var(--gris3);font-weight:800;">OFF</span>
            <span class="switch-shell">
              <input type="checkbox" id="bf-event-switch" class="event-switch-input" aria-label="Activar Black Friday">
              <span class="switch-track"></span>
              <span class="switch-thumb"></span>
            </span>
            <span class="switch-on" style="font-size:12px;color:var(--amarillo-texto);font-weight:800;">ON</span>
          </label>
        </div>
      </div>
    </div>
  </main>
</div>
</div>

<?php include '../includes/footer.php'; ?>
