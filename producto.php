<?php
require_once 'includes/config.php';
$pdo = getDB();

// 1) Leer el ID del producto desde la URL (?id=123)
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ' . SITE_URL . '/productos.php');
    exit;
}

// 2) Buscar el producto por su ID (esto es lo que faltaba)
$stmt = $pdo->prepare("SELECT p.*, c.id as cat_id, c.nombre as cat_nombre, c.icono as cat_icono
                        FROM productos p
                        JOIN categorias c ON p.categoria_id = c.id
                        WHERE p.id = ? AND p.activo = 1");
$stmt->execute([$id]);
$p = $stmt->fetch();

// 3) Si no existe (o está inactivo), redirigir al listado
if (!$p) {
    header('Location: ' . SITE_URL . '/productos.php');
    exit;
}

$precio = $p['precio_oferta'] ?? $p['precio'];
$desc   = $p['precio_oferta'] ? round((1 - $p['precio_oferta'] / $p['precio']) * 100) : 0;
$enStock = (float)$p['stock'] > 0; // stock es varchar tipo "20+"

// Imágenes: principal + hover + extras (JSON)
$imagenes = [];
if (!empty($p['imagen'])) $imagenes[] = $p['imagen'];
if (!empty($p['imagen2'])) $imagenes[] = $p['imagen2'];
if (!empty($p['imagenes_extra'])) {
    $extra = json_decode($p['imagenes_extra'], true);
    if (is_array($extra)) {
        foreach ($extra as $img) {
            if (!empty($img)) $imagenes[] = $img;
        }
    }
}
$imagenes = array_values(array_unique(array_filter($imagenes)));

// Especificaciones: texto tipo "Etiqueta: valor" por línea -> tabla
$specs = [];
if (!empty($p['especificaciones'])) {
    $lineas = preg_split('/\r\n|\r|\n/', $p['especificaciones']);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if ($linea === '') continue;
        $partes = explode(':', $linea, 2);
        if (count($partes) === 2) {
            $specs[] = ['label' => trim($partes[0]), 'valor' => trim($partes[1])];
        } else {
            $specs[] = ['label' => '', 'valor' => $linea];
        }
    }
}

// Reseñas del producto
try {
    $rs = $pdo->prepare("SELECT r.*, u.nombre, u.apellido
                          FROM resenas r
                          JOIN usuarios u ON u.id = r.usuario_id
                          WHERE r.producto_id = ?
                          ORDER BY r.creado_en DESC
                          LIMIT 20");
    $rs->execute([$id]);
    $resenas = $rs->fetchAll();
    $promedio = 0;
    if ($resenas) {
        $suma = array_sum(array_column($resenas, 'calificacion'));
        $promedio = round($suma / count($resenas), 1);
    }
} catch (Exception $e) {
    $resenas = [];
    $promedio = 0;
}

// Productos relacionados (misma categoría, excluyendo el actual)
try {
    $rel = $pdo->prepare("SELECT p.*, CASE WHEN p.precio_oferta IS NOT NULL THEN ROUND((1-p.precio_oferta/p.precio)*100) ELSE 0 END as descuento
                           FROM productos p
                           WHERE p.categoria_id = ? AND p.id != ? AND p.activo = 1
                           ORDER BY p.destacado DESC, p.creado_en DESC
                           LIMIT 4");
    $rel->execute([$p['categoria_id'], $id]);
    $relacionados = $rel->fetchAll();
} catch (Exception $e) {
    $relacionados = [];
}

$pageTitle = $p['nombre'];
include 'includes/header.php';
?>
<style>
.pd-wrap {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin: 30px 0 50px;
}
@media (max-width: 900px) {
    .pd-wrap { grid-template-columns: 1fr; }
}
.pd-gallery-main {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    background: #f5f5f7;
    border-radius: var(--rl);
    border: 1.5px solid var(--borde);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}
.pd-gallery-main img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.pd-gallery-main .prod-ph i { font-size: 72px; color: #ccc; }
.pd-thumbs {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}
.pd-thumbs img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 10px;
    border: 2px solid var(--borde);
    cursor: pointer;
    transition: border-color .2s;
}
.pd-thumbs img.activa,
.pd-thumbs img:hover { border-color: #CEFF04; }

.pd-brand { font-size: 13px; font-weight: 700; color: var(--gris3); text-transform: uppercase; letter-spacing: .5px; }
.pd-title { font-size: 26px; font-weight: 900; color: #0d0d0d; margin: 6px 0 10px; }
.pd-modelo { font-size: 13px; color: var(--gris3); margin-bottom: 14px; }
.pd-rating { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; color: #f5a623; font-size: 14px; }
.pd-price-box { background: #fff; border: 1.5px solid var(--borde); border-radius: var(--rl); padding: 20px; margin-bottom: 20px; }
.pd-price-box .price-old { display: block; text-decoration: line-through; color: var(--gris3); font-size: 15px; }
.pd-price-box .price-main { display: block; font-size: 30px; font-weight: 900; color: #0d0d0d; }
.pd-price-box .price-save { display: inline-block; margin-left: 10px; font-size: 13px; color: #2e7d32; font-weight: 700; }
.pd-price-box .prod-stock { margin: 10px 0; font-size: 13px; color: #2e7d32; font-weight: 600; }
.pd-price-box .prod-stock.out { color: #c62828; }
.pd-desc { line-height: 1.7; color: var(--gris2); white-space: pre-line; margin-bottom: 24px; }
.pd-badge-desc {
    display: inline-block; background: #e53935; color: #fff; font-size: 12px; font-weight: 800;
    padding: 4px 10px; border-radius: 6px; margin-bottom: 10px;
}
.pd-section { margin: 40px 0; }
.pd-section h2 { font-size: 20px; font-weight: 800; margin-bottom: 16px; color: #0d0d0d; }
.pd-specs-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: var(--rl); overflow: hidden; border: 1.5px solid var(--borde); }
.pd-specs-table tr:nth-child(odd) { background: #fafafa; }
.pd-specs-table td { padding: 10px 16px; font-size: 14px; border-bottom: 1px solid var(--borde); }
.pd-specs-table td:first-child { font-weight: 700; color: var(--gris2); width: 40%; }
.pd-review { border-bottom: 1px solid var(--borde); padding: 14px 0; }
.pd-review-head { display: flex; justify-content: space-between; font-size: 13px; color: var(--gris3); margin-bottom: 4px; }
.pd-review-stars { color: #f5a623; }
.pd-related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
</style>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= SITE_URL ?>/index.php">Inicio</a><span>›</span>
    <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $p['cat_id'] ?>"><?= sanitize($p['cat_nombre']) ?></a><span>›</span>
    <strong><?= sanitize($p['nombre']) ?></strong>
  </div>

  <div class="pd-wrap">
    <!-- GALERÍA -->
    <div>
      <div class="pd-gallery-main" id="pd-main">
        <?php if (!empty($imagenes)): ?>
          <img id="pd-main-img" src="<?= SITE_URL ?>/<?= htmlspecialchars($imagenes[0]) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>"
               onerror="this.style.display='none';">
        <?php else: ?>
          <div class="prod-ph"><i class="fas <?= htmlspecialchars($p['cat_icono']) ?>"></i></div>
        <?php endif; ?>
      </div>
      <?php if (count($imagenes) > 1): ?>
      <div class="pd-thumbs">
        <?php foreach ($imagenes as $i => $img): ?>
          <img src="<?= SITE_URL ?>/<?= htmlspecialchars($img) ?>"
               class="<?= $i === 0 ? 'activa' : '' ?>"
               onclick="document.getElementById('pd-main-img').src=this.src; document.querySelectorAll('.pd-thumbs img').forEach(t=>t.classList.remove('activa')); this.classList.add('activa');">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- INFO -->
    <div>
      <div class="pd-brand"><?= htmlspecialchars($p['marca']) ?></div>
      <h1 class="pd-title"><?= htmlspecialchars($p['nombre']) ?></h1>
      <?php if (!empty($p['modelo'])): ?>
        <div class="pd-modelo">Modelo: <?= htmlspecialchars($p['modelo']) ?></div>
      <?php endif; ?>

      <?php if ($resenas): ?>
        <div class="pd-rating">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="fas fa-star" style="color:<?= $i <= round($promedio) ? '#f5a623' : '#ddd' ?>;"></i>
          <?php endfor; ?>
          <span style="color:var(--gris3);"><?= $promedio ?> (<?= count($resenas) ?> reseña<?= count($resenas) == 1 ? '' : 's' ?>)</span>
        </div>
      <?php endif; ?>

      <div class="pd-price-box">
        <?php if ($desc): ?><span class="pd-badge-desc"><?= $desc ?>% OFF</span><?php endif; ?>
        <?= renderPrecioCarrito($p, $precio, $desc, $p['id']) ?>
      </div>

      <?php if (!empty($p['descripcion'])): ?>
        <div class="pd-desc"><?= nl2br(htmlspecialchars($p['descripcion'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($specs)): ?>
  <div class="pd-section">
    <h2><i class="fas fa-list-ul"></i> Especificaciones técnicas</h2>
    <table class="pd-specs-table">
      <?php foreach ($specs as $s): ?>
        <tr>
          <td><?= htmlspecialchars($s['label']) ?></td>
          <td><?= htmlspecialchars($s['valor']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($resenas): ?>
  <div class="pd-section">
    <h2><i class="fas fa-comment"></i> Reseñas de clientes</h2>
    <?php foreach ($resenas as $r): ?>
      <div class="pd-review">
        <div class="pd-review-head">
          <strong style="color:var(--gris1);"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></strong>
          <span><?= date('d/m/Y', strtotime($r['creado_en'])) ?></span>
        </div>
        <div class="pd-review-stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="fas fa-star" style="color:<?= $i <= $r['calificacion'] ? '#f5a623' : '#ddd' ?>;"></i>
          <?php endfor; ?>
        </div>
        <?php if (!empty($r['comentario'])): ?>
          <p style="margin-top:6px;color:var(--gris2);"><?= nl2br(htmlspecialchars($r['comentario'])) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($relacionados)): ?>
  <div class="pd-section">
    <h2><i class="fas fa-th-large"></i> Productos relacionados</h2>
    <div class="pd-related-grid">
      <?php foreach ($relacionados as $rp):
          $rprecio = $rp['precio_oferta'] ?? $rp['precio'];
      ?>
        <div class="prod-card" data-id="<?= $rp['id'] ?>" style="position:relative;">
          <a class="prod-card-link" href="<?= SITE_URL ?>/producto.php?id=<?= $rp['id'] ?>" style="position:absolute;inset:0;z-index:1;"></a>
          <div class="prod-img" style="position:relative;height:160px;background:#f5f5f7;display:flex;align-items:center;justify-content:center;border-radius:12px 12px 0 0;overflow:hidden;">
            <?php if (!empty($rp['imagen'])): ?>
              <img src="<?= SITE_URL ?>/<?= htmlspecialchars($rp['imagen']) ?>" alt="<?= htmlspecialchars($rp['nombre']) ?>" style="width:100%;height:100%;object-fit:cover;" loading="lazy">
            <?php endif; ?>
          </div>
          <div class="prod-body" style="padding:12px;">
            <div class="prod-brand"><?= htmlspecialchars($rp['marca']) ?></div>
            <div class="prod-name"><a href="<?= SITE_URL ?>/producto.php?id=<?= $rp['id'] ?>"><?= htmlspecialchars($rp['nombre']) ?></a></div>
            <?= renderPrecioCarrito($rp, $rprecio, $rp['descuento'], $rp['id']) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const SITE_URL = '<?= SITE_URL ?>';

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-add-cart');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    const id   = btn.dataset.id;
    const orig = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';

    fetch(SITE_URL + '/ajax/carrito.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=agregar&producto_id=' + id + '&cantidad=1'
    })
    .then(r => r.text())
    .then(text => {
        const start = text.indexOf('{');
        const d = JSON.parse(start !== -1 ? text.slice(start) : text);
        if (d.success) {
            btn.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
            btn.style.background = '#22c55e';
            btn.style.color = '#fff';
            if (typeof updateBadge === 'function') updateBadge(d.cart_count);
            if (typeof showToast === 'function') showToast('✓ Producto agregado al carrito', true);
        } else {
            btn.innerHTML = orig;
            if (typeof showToast === 'function') showToast(d.message || 'Error al agregar', false);
            else alert(d.message || 'Error al agregar al carrito');
        }
    })
    .catch(() => {
        btn.innerHTML = orig;
        if (typeof showToast === 'function') showToast('Error de conexión', false);
    })
    .finally(() => {
        setTimeout(() => {
            btn.disabled = false;
            btn.style.background = '';
            btn.style.color = '';
            btn.innerHTML = '<i class="fas fa-cart-plus"></i> Agregar al carrito';
        }, 2000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>