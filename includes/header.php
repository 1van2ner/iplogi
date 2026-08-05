<?php
if (!function_exists('getDB')) {
  require_once dirname(__DIR__) . '/includes/config.php';
}
$pageTitle = $pageTitle ?? 'IP Tecnología Perú';
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('countCarrito')) {
  function countCarrito()
  {
    $pdo = getDB();
    if (isLoggedIn()) {
      $s = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM carrito WHERE usuario_id=?");
      $s->execute([$_SESSION['usuario_id']]);
    } else {
      $s = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM carrito WHERE session_id=?");
      $s->execute([session_id()]);
    }
    return (int)$s->fetchColumn();
  }
}
$carritoCount = countCarrito();
$userPoints = 0;
if (isLoggedIn()) {
  try {
    $stmtPoints = getDB()->prepare("SELECT puntos FROM usuarios WHERE id = ?");
    $stmtPoints->execute([$_SESSION['usuario_id']]);
    $userPoints = (int)$stmtPoints->fetchColumn();
  } catch (Exception $e) {
    $userPoints = 0;
  }
}
$pdo2 = getDB();
$pdo2 = getDB();
try {
  $cols2 = $pdo2->query("SHOW COLUMNS FROM categorias")->fetchAll(PDO::FETCH_COLUMN);
  if (in_array('padre_id', $cols2)) {
    // Traer TODAS las categorías manteniendo padre_id real (NULL para raíces)
    $cats = $pdo2->query("SELECT c.id, c.nombre, c.icono,
      c.padre_id,
      (SELECT COUNT(*) FROM productos WHERE categoria_id=c.id AND activo=1) as total
      FROM categorias c WHERE c.nombre <> '__PROMOCIONES__' ORDER BY COALESCE(c.padre_id,0), c.nombre")->fetchAll();
  } else {
    $cats = $pdo2->query("SELECT c.id, c.nombre, c.icono, NULL as padre_id,
      (SELECT COUNT(*) FROM productos WHERE categoria_id=c.id AND activo=1) as total
      FROM categorias c WHERE c.nombre <> '__PROMOCIONES__' ORDER BY c.nombre")->fetchAll();
  }
} catch(Exception $e) { $cats = []; }

if (isset($_SESSION['flash_message'])) {
  $fm = $_SESSION['flash_message'];
  $ft = $_SESSION['flash_type'] ?? 'info';
  unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= sanitize($pageTitle) ?> | IP Tecnología Perú</title>
  <link rel="icon" href="assets/img/logo.ico">
  <meta name="description" content="IP Tecnología Perú - Cámaras IP, routers, impresoras y UPS.">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>

<body>

  <header class="header">
    <div class="container">
      <a href="<?= SITE_URL ?>" class="logo">
        <img src="/assets/img/logop.jpg" alt="IP Tecnología Perú" class="logo-img">
        <div class="logo-text">
          <span class="top">Tecnología &amp; Seguridad</span>
          <span class="bottom"><span>Tecnología</span> <em>Perú</em></span>
        </div>
      </a>
      <form action="<?= SITE_URL ?>/productos.php" method="GET" class="search-bar">
        <input type="text" name="q" value="<?= sanitize($_GET['q'] ?? '') ?>" placeholder="Buscar cámaras, routers, impresoras, UPS...">
        <button type="submit"><i class="fas fa-search"></i></button>
      </form>
      <div class="header-actions">
        <?php if (isLoggedIn()): ?>
          <div class="user-wrap">
            <button class="user-btn btn-header-action">
              <i class="fas fa-user-circle"></i>
              <span><?= sanitize(explode(' ', $_SESSION['nombre'])[0]) ?></span>
              <span class="user-points"><?= number_format($userPoints) ?> pts</span>
              <i class="fas fa-chevron-down" style="font-size:10px;"></i>
            </button>
            <div class="user-dropdown">
              <a href="<?= SITE_URL ?>/perfil.php"><i class="fas fa-user" style="color:var(--primary);width:16px;"></i> Mi Perfil</a>
              <a href="<?= SITE_URL ?>/mis-pedidos.php"><i class="fas fa-box" style="color:var(--primary);width:16px;"></i> Mis Pedidos</a>
              <?php if (isAdmin()): ?>
                <a href="<?= SITE_URL ?>/admin/index.php"><i class="fas fa-cog" style="color:var(--primary);width:16px;"></i> Panel Admin</a>
              <?php endif; ?>
              <a href="<?= SITE_URL ?>/logout.php" class="danger"><i class="fas fa-sign-out-alt" style="color:var(--danger);width:16px;"></i> Cerrar Sesión</a>
            </div>
          </div>
        <?php else: ?>
          <a href="<?= SITE_URL ?>/login.php" class="btn-header-action btn-login"><i class="fas fa-sign-in-alt"></i> Ingresar</a>
          <a href="<?= SITE_URL ?>/registro.php" class="btn-header-action btn-register"><i class="fas fa-user-plus"></i> Registrarse</a>
        <?php endif; ?>
        <a href="<?= SITE_URL ?>/carrito.php" class="cart-btn" title="Carrito">
          <i class="fas fa-shopping-cart"></i>
          <?php if ($carritoCount > 0): ?><span class="cart-badge"><?= $carritoCount ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </header>
  <nav class="main-nav">
    <div class="container">
      <div class="cat-menu">
        <button class="cat-btn"><i class="fas fa-th-large"></i> Categorías <i class="fas fa-chevron-down" style="font-size:10px;margin-left:4px;"></i></button>
        <div class="cat-dropdown cat-dropdown-tree">
          <?php
          // Raíces = padre_id NULL. Hijos = padre_id con valor entero
          $raices = array_filter($cats, fn($c) => is_null($c['padre_id']));
          $hijos  = [];
          foreach ($cats as $c) {
            if (!is_null($c['padre_id'])) $hijos[(int)$c['padre_id']][] = $c;
          }
          foreach ($raices as $cat):
            $tieneHijos = isset($hijos[$cat['id']]);
          ?>
          <div class="cat-tree-item <?= $tieneHijos ? 'has-children' : '' ?>">
            <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $cat['id'] ?>" class="cat-tree-link cat-tree-root">
              <i class="fas <?= sanitize($cat['icono']) ?>"></i>
              <span><?= sanitize($cat['nombre']) ?></span>
              <?php if ($tieneHijos): ?><i class="fas fa-chevron-right cat-tree-arrow"></i><?php endif; ?>
            </a>
            <?php if ($tieneHijos): ?>
            <div class="cat-tree-sub">
              <?php foreach ($hijos[$cat['id']] as $hijo):
                $tieneNietos = isset($hijos[$hijo['id']]);
              ?>
              <div class="cat-tree-item <?= $tieneNietos ? 'has-children' : '' ?>">
                <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $hijo['id'] ?>" class="cat-tree-link cat-tree-child">
                  <i class="fas <?= sanitize($hijo['icono']) ?>"></i>
                  <span><?= sanitize($hijo['nombre']) ?></span>
                  <?php if ($tieneNietos): ?><i class="fas fa-chevron-right cat-tree-arrow"></i><?php endif; ?>
                </a>
                <?php if ($tieneNietos): ?>
                <div class="cat-tree-sub cat-tree-sub2">
                  <?php foreach ($hijos[$hijo['id']] as $nieto): ?>
                  <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $nieto['id'] ?>" class="cat-tree-link cat-tree-grandchild">
                    <i class="fas <?= sanitize($nieto['icono']) ?>"></i>
                    <span><?= sanitize($nieto['nombre']) ?></span>
                  </a>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <a href="<?= SITE_URL ?>/productos.php" class="cat-tree-link cat-tree-all">
            <i class="fas fa-th"></i><span>Ver todos los productos</span>
          </a>
        </div>
      </div>
      <ul class="nav-links">
        <li><a href="/" <?= $currentPage === 'index.php' ? 'class="active"' : '' ?>>Inicio</a></li>
        <li><a href="<?= SITE_URL ?>/productos.php" <?= $currentPage === 'productos.php' ? 'class="active"' : '' ?>>Productos</a></li>
        <li><a href="<?= SITE_URL ?>/productos.php?categoria=1"><i class="fas fa-camera"></i> Cámaras</a></li>
        <li><a href="<?= SITE_URL ?>/productos.php?categoria=2"><i class="fas fa-bell"></i> Alarmas</a></li>
        <li><a href="<?= SITE_URL ?>/productos.php?categoria=5"><i class="fas fa-network-wired"></i> Redes</a></li>
        <li><a href="<?= SITE_URL ?>/productos.php?categoria=9"><i class="fas fa-bolt"></i> Energía</a></li>
        <li><a href="<?= SITE_URL ?>/contacto.php" <?= $currentPage === 'contacto.php' ? 'class="active"' : '' ?>>Contacto</a></li>
      </ul>
    </div>

  </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function() {

      // ── Dropdown USUARIO (click) ──────────────────────────────
      const userWrap = document.querySelector('.user-wrap');
      const userBtn = document.querySelector('.user-btn');
      if (userWrap && userBtn) {
        userBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          userWrap.classList.toggle('open');
          catMenu?.classList.remove('open'); // cerrar categorías si estaba abierto
        });
        userWrap.querySelector('.user-dropdown')?.addEventListener('click', e => e.stopPropagation());
      }

      // ── Dropdown CATEGORÍAS (click) ───────────────────────────
      const catMenu = document.querySelector('.cat-menu');
      const catBtn = document.querySelector('.cat-btn');
      if (catMenu && catBtn) {
        catBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          catMenu.classList.toggle('open');
          userWrap?.classList.remove('open'); // cerrar usuario si estaba abierto
        });
        catMenu.querySelector('.cat-dropdown')?.addEventListener('click', e => e.stopPropagation());
      }

      // ── Cerrar ambos al hacer click fuera ─────────────────────
      document.addEventListener('click', function() {
        userWrap?.classList.remove('open');
        catMenu?.classList.remove('open');
      });

    });
  </script>

  <?php if (isset($fm)): ?>
    <div class="flash flash-<?= $ft ?>">
      <i class="fas fa-<?= $ft === 'success' ? 'check-circle' : ($ft === 'error' ? 'times-circle' : 'info-circle') ?>"></i>
      <?= sanitize($fm) ?>
      <button onclick="this.parentElement.remove()">×</button>
    </div>
  <?php endif; ?>