<?php
require_once 'includes/config.php';
$pdo = getDB();

if (isLoggedIn()) {
    $s = $pdo->prepare("SELECT c.*, p.nombre, p.precio, p.precio_oferta, p.stock, p.marca, p.imagen, cat.icono
                        FROM carrito c
                        JOIN productos p ON c.producto_id = p.id
                        JOIN categorias cat ON p.categoria_id = cat.id
                        WHERE c.usuario_id = ? AND p.activo = 1
                        ORDER BY c.creado_en DESC");
    $s->execute([$_SESSION['usuario_id']]);
} else {
    $s = $pdo->prepare("SELECT c.*, p.nombre, p.precio, p.precio_oferta, p.stock, p.marca, p.imagen, cat.icono
                        FROM carrito c
                        JOIN productos p ON c.producto_id = p.id
                        JOIN categorias cat ON p.categoria_id = cat.id
                        WHERE c.session_id = ? AND p.activo = 1
                        ORDER BY c.creado_en DESC");
    $s->execute([session_id()]);
}
$items = $s->fetchAll();

$subtotal = 0;
foreach ($items as $item) {
    $precio    = ($item['precio_oferta'] && $item['precio_oferta'] > 0) ? $item['precio_oferta'] : $item['precio'];
    $precio    = precioFinal($precio);
    $subtotal += $precio * $item['cantidad'];
}
$envio = $subtotal >= 200 ? 0 : 15;
$total = $subtotal + $envio;

$pageTitle = 'Mi Carrito';
include 'includes/header.php';
?>

<style>
/* ── Carrito rediseñado ─────────────────────────────────────── */
.carrito-wrap   { padding: 20px 0 60px; }
.carrito-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; align-items: start; }

/* Tabla de items */
.carrito-tabla  { background:#fff; border:1.5px solid #e0e0e4; border-radius:14px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.06); }
.carrito-head   { display:grid; grid-template-columns:64px 1fr 120px 130px 110px 44px;
                  gap:10px; padding:12px 18px; background:#f5f5f7;
                  border-bottom:1px solid #e0e0e4; font-size:11px; font-weight:700;
                  text-transform:uppercase; letter-spacing:.5px; color:#888; }
.carrito-fila   { display:grid; grid-template-columns:64px 1fr 120px 130px 110px 44px;
                  gap:10px; padding:14px 18px; align-items:center;
                  border-bottom:1px solid #f0f0f2; transition:background .15s; }
.carrito-fila:last-child { border-bottom:none; }
.carrito-fila:hover      { background:#fafafa; }

/* Ícono producto */
.c-img { width:56px; height:56px; background:#f0f0f2; border-radius:10px;
         display:flex; align-items:center; justify-content:center; font-size:24px; color:#6b7300; flex-shrink:0; }

/* Info producto */
.c-nombre { font-size:13px; font-weight:700; color:#0d0d0d; line-height:1.35; }
.c-nombre a { color:#0d0d0d; text-decoration:none; }
.c-nombre a:hover { color:#6b7300; }
.c-marca  { font-size:11px; color:#6b7300; font-weight:600; margin-top:3px; }
.c-tachado{ font-size:11px; color:#aaa; text-decoration:line-through; margin-top:1px; }

/* Precio unitario */
.c-precio { font-size:15px; font-weight:800; color:#6b7300; white-space:nowrap; }

/* Cantidad */
.c-qty    { display:flex; align-items:center; border:1.5px solid #e0e0e4; border-radius:8px; overflow:hidden; width:fit-content; }
.c-qty-btn{ width:32px; height:36px; background:#f5f5f7; border:none; cursor:pointer;
            font-size:18px; font-weight:900; color:#555; display:flex; align-items:center;
            justify-content:center; transition:background .15s; }
.c-qty-btn:hover { background:#D7E022; color:#000; }
.c-qty-input{ width:42px; height:36px; text-align:center; background:#fff; border:none;
              border-left:1px solid #e0e0e4; border-right:1px solid #e0e0e4;
              font-size:14px; font-weight:700; color:#0d0d0d; }

/* Subtotal */
.c-sub  { font-size:15px; font-weight:900; color:#0d0d0d; white-space:nowrap; }

/* Botón eliminar */
.c-del  { width:36px; height:36px; background:none; border:1.5px solid #e0e0e4;
          border-radius:8px; cursor:pointer; color:#aaa; font-size:14px;
          display:flex; align-items:center; justify-content:center; transition:all .15s; }
.c-del:hover { background:#fee2e2; border-color:#fca5a5; color:#c62828; }

/* Resumen */
.carrito-resumen { background:#fff; border:1.5px solid #e0e0e4; border-radius:14px;
                   padding:22px; box-shadow:0 2px 12px rgba(0,0,0,.06); position:sticky; top:150px; }
.resumen-titulo  { font-size:16px; font-weight:800; color:#0d0d0d; margin-bottom:18px;
                   padding-bottom:12px; border-bottom:1px solid #e0e0e4; display:flex; align-items:center; gap:8px; }
.resumen-fila    { display:flex; justify-content:space-between; align-items:center;
                   padding:8px 0; font-size:14px; color:#555; }
.resumen-fila .lbl { color:#333; font-weight:500; }
.resumen-fila.total{ font-size:18px; font-weight:900; color:#0d0d0d; padding-top:14px;
                     margin-top:6px; border-top:2px solid #e0e0e4; }
.resumen-fila.total .val { color:#6b7300; }
.info-envio-gratis { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;
                     padding:10px 12px; font-size:12px; color:#166534; margin:10px 0; }
.info-envio-falta  { background:#fffbeb; border:1px solid #CEFF04; border-radius:8px;
                     padding:10px 12px; font-size:12px; color:#92400e; margin:10px 0; }

/* Carrito vacío */
.carrito-vacio { text-align:center; padding:80px 20px; }
.carrito-vacio i  { font-size:80px; color:#ddd; display:block; margin-bottom:20px; }
.carrito-vacio h2 { font-size:22px; font-weight:800; color:#0d0d0d; margin-bottom:8px; }
.carrito-vacio p  { color:#888; margin-bottom:24px; }

/* Métodos de pago */
.pay-icon { background:#f5f5f7; border:1px solid #e0e0e4; border-radius:6px;
            padding:6px 12px; font-size:18px; display:inline-flex; align-items:center; }

@media(max-width:900px) {
  .carrito-layout { grid-template-columns: 1fr; }
  .carrito-head   { display: none; }
  .carrito-fila   {
    grid-template-columns: 64px 1fr 44px;
    grid-template-rows: auto auto auto;
    gap: 8px 12px;
    padding: 14px 12px;
  }

  /* Imagen — col 1, ocupa 3 filas */
  .carrito-fila > div:nth-child(1) {
    grid-column: 1;
    grid-row: 1 / 4;
  }

  /* Nombre + marca — col 2, fila 1 */
  .carrito-fila > div:nth-child(2) {
    grid-column: 2;
    grid-row: 1;
  }

  /* Precio unitario — col 2, fila 2 */
  .carrito-fila .c-precio {
    grid-column: 2;
    grid-row: 2;
    font-size: 13px;
  }

  /* Cantidad (+/-) — col 2, fila 3 */
  .carrito-fila > div:nth-child(4) {
    grid-column: 2;
    grid-row: 3;
  }

  /* Subtotal — oculto en móvil pequeño */
  .carrito-fila .c-sub {
    display: none;
  }

  /* Botón eliminar — col 3, fila 1 */
  .carrito-fila .c-del {
    grid-column: 3;
    grid-row: 1;
  }

  /* Botones cantidad más grandes para touch */
  .c-qty-btn {
    width: 36px !important;
    height: 40px !important;
    font-size: 20px !important;
  }
  .c-qty-input {
    width: 44px !important;
    height: 40px !important;
    font-size: 14px !important;
  }
}
</style>

<div class="container carrito-wrap">

  <div class="breadcrumb">
    <a href="<?= SITE_URL ?>/index.php">Inicio</a><span>›</span>
    <strong>Mi Carrito</strong>
  </div>

  <h1 class="page-title">
    <i class="fas fa-shopping-cart"></i> Mi Carrito
    <?php if(count($items) > 0): ?>
      <span style="font-size:15px;color:#888;font-weight:400;">(<?= count($items) ?> <?= count($items)===1?'producto':'productos' ?>)</span>
    <?php endif; ?>
  </h1>

  <?php if (empty($items)): ?>
  <div class="carrito-vacio">
    <i class="fas fa-shopping-cart"></i>
    <h2>Tu carrito está vacío</h2>
    <p>Agrega productos para continuar con tu compra</p>
    <a href="<?= SITE_URL ?>/productos.php" class="btn-main btn-ver-productos"><i class="fas fa-shopping-bag"></i> Ver productos</a>
  </div>

  <?php else: ?>
  <div class="carrito-layout">

    <!-- ── LISTA DE PRODUCTOS ─────────────────────────────── -->
    <div>
      <div class="carrito-tabla">

        <!-- Cabecera (solo desktop) -->
        <div class="carrito-head">
          <div></div>
          <div>Producto</div>
          <div>Precio unit.</div>
          <div>Cantidad</div>
          <div>Subtotal</div>
          <div></div>
        </div>

        <!-- Filas -->
        <?php foreach ($items as $item):
          $precio = ($item['precio_oferta'] && $item['precio_oferta'] > 0) ? $item['precio_oferta'] : $item['precio'];
          $precio = precioFinal($precio);
          $precioTachado = precioFinal($item['precio']);
          $sub    = $precio * $item['cantidad'];
        ?>
        <div class="carrito-fila" id="item-<?= $item['id'] ?>">

          <!-- Ícono -->
          <div class="c-img">
            <?php if (!empty($item['imagen'])): ?>
              <img src="<?= SITE_URL ?>/<?= sanitize($item['imagen']) ?>" alt="<?= sanitize($item['nombre']) ?>" style="width:100%;height:100%;object-fit:contain;padding:6px;">
            <?php else: ?>
              <i class="fas <?= sanitize($item['icono']) ?>"></i>
            <?php endif; ?>
          </div>

          <!-- Nombre + marca -->
          <div>
            <div class="c-nombre">
              <a href="<?= SITE_URL ?>/producto.php?id=<?= $item['producto_id'] ?>"><?= sanitize($item['nombre']) ?></a>
            </div>
            <div class="c-marca"><?= sanitize($item['marca']) ?></div>
            <?php if ($item['precio_oferta'] && $item['precio_oferta'] > 0): ?>
              <div class="c-tachado"><?= formatPrice($precioTachado) ?></div>
            <?php endif; ?>
          </div>

          <!-- Precio unitario -->
          <div class="c-precio"><?= formatPrice($precio) ?></div>

          <!-- Cantidad -->
          <div>
            <div class="c-qty">
              <button class="c-qty-btn" onclick="cambiarCantidad(<?= $item['id'] ?>, -1)">−</button>
              <input class="c-qty-input" type="number"
                     id="qty-<?= $item['id'] ?>"
                     value="<?= $item['cantidad'] ?>"
                     min="1" max="<?= $item['stock'] ?>"
                     onchange="setCantidad(<?= $item['id'] ?>, this.value)">
              <button class="c-qty-btn" onclick="cambiarCantidad(<?= $item['id'] ?>, 1)">+</button>
            </div>
          </div>

          <!-- Subtotal -->
          <div class="c-sub" id="sub-<?= $item['id'] ?>"><?= formatPrice($sub) ?></div>

          <!-- Eliminar -->
          <div>
            <button class="c-del" onclick="eliminarItem(<?= $item['id'] ?>)" title="Eliminar">
              <i class="fas fa-trash-alt"></i>
            </button>
          </div>

        </div>
        <?php endforeach; ?>
      </div>

      <!-- Acciones debajo de la tabla -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;flex-wrap:wrap;gap:10px;">
        <a href="<?= SITE_URL ?>/productos.php" class="btn-sec" style="padding:9px 18px;font-size:13px;">
          <i class="fas fa-arrow-left"></i> Seguir comprando
        </a>
        <button onclick="vaciarCarrito()"
                style="background:none;border:1.5px solid #e0e0e4;color:#888;padding:9px 18px;
                       border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;transition:all .2s;"
                onmouseover="this.style.borderColor='#fca5a5';this.style.color='#c62828';"
                onmouseout="this.style.borderColor='#e0e0e4';this.style.color='#888';">
          <i class="fas fa-trash"></i> Vaciar carrito
        </button>
      </div>
    </div>

    <!-- ── RESUMEN ────────────────────────────────────────── -->
    <div class="carrito-resumen">
      <div class="resumen-titulo">
        <i class="fas fa-receipt" style="color:#6b7300;"></i> Resumen del pedido
      </div>

      <div class="resumen-fila">
        <span class="lbl">Subtotal</span>
        <span id="resumen-sub"><?= formatPrice($subtotal) ?></span>
      </div>
      <div class="resumen-fila">
        <span class="lbl">Envío</span>
        <span id="resumen-envio">
          <?= $envio == 0
            ? '<span style="color:#166534;font-weight:700;">GRATIS</span>'
            : formatPrice($envio) ?>
        </span>
      </div>

      <div id="info-envio">
        <?php if ($subtotal < 200): ?>
        <div class="info-envio-falta">
          <i class="fas fa-info-circle"></i>
          Agrega <strong><?= formatPrice(200 - $subtotal) ?></strong> más para envío gratis
        </div>
        <?php else: ?>
        <div class="info-envio-gratis">
          <i class="fas fa-check-circle"></i> ¡Tienes envío gratis!
        </div>
        <?php endif; ?>
      </div>

      <div class="resumen-fila total">
        <span>Total</span>
        <span class="val" id="resumen-total"><?= formatPrice($total) ?></span>
      </div>

      <a href="<?= SITE_URL ?>/checkout.php" class="btn-checkout" style="margin-top:18px;">
        <i class="fas fa-lock"></i> Proceder al pago
      </a>

      <!-- Métodos de pago -->
      <div style="margin-top:16px;text-align:center;">
        <div style="font-size:11px;color:#aaa;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Aceptamos</div>
        <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
          <span class="pay-icon" style="color:#1a1f71;"><i class="fab fa-cc-visa"></i></span>
          <span class="pay-icon" style="color:#eb001b;"><i class="fab fa-cc-mastercard"></i></span>
          <span class="pay-icon" style="color:#6b7300;"><i class="fas fa-mobile-alt"></i> Yape</span>
          <span class="pay-icon" style="color:#0277bd;"><i class="fas fa-university"></i></span>
        </div>
      </div>
    </div>

  </div><!-- /carrito-layout -->
  <?php endif; ?>
</div>

<script>
const SITE_URL = '<?= SITE_URL ?>';

// ── Helper: fetch con texto (evita crash por warnings de PHP) ─
function fetchCarrito(body, callback) {
  fetch(SITE_URL + '/ajax/carrito.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    body
  })
  .then(r => r.text())
  .then(text => {
    const start = text.indexOf('{');
    if (start === -1) { console.error('Respuesta inválida:', text); return; }
    try {
      callback(JSON.parse(text.slice(start)));
    } catch(e) { console.error('JSON inválido:', text); }
  })
  .catch(err => console.error('Error fetch:', err));
}

// ── Actualizar resumen lateral ────────────────────────────────
function actualizarResumen() {
  fetchCarrito('action=resumen', function(d) {
    if (d.subtotal === undefined) return;

    document.getElementById('resumen-sub').textContent   = 'S/ ' + d.subtotal.toFixed(2);
    document.getElementById('resumen-total').textContent = 'S/ ' + d.total.toFixed(2);
    document.getElementById('resumen-envio').innerHTML   = d.envio == 0
      ? '<span style="color:#166534;font-weight:700;">GRATIS</span>'
      : 'S/ ' + d.envio.toFixed(2);

    const infoDiv = document.getElementById('info-envio');
    if (infoDiv) {
      if (d.envio == 0) {
        infoDiv.innerHTML = '<div class="info-envio-gratis"><i class="fas fa-check-circle"></i> ¡Tienes envío gratis!</div>';
      } else {
        const falta = (200 - d.subtotal).toFixed(2);
        infoDiv.innerHTML = '<div class="info-envio-falta"><i class="fas fa-info-circle"></i> Agrega <strong>S/ ' + falta + '</strong> más para envío gratis</div>';
      }
    }

    if (typeof updateBadge === 'function') updateBadge(d.count);
  });
}

// ── Cambiar cantidad con botones +/- ─────────────────────────
function cambiarCantidad(itemId, delta) {
  const input = document.getElementById('qty-' + itemId);
  const max   = parseInt(input.max) || 999;
  const nueva = Math.min(max, Math.max(1, parseInt(input.value) + delta));
  input.value = nueva;
  setCantidad(itemId, nueva);
}

// ── Actualizar cantidad (llamado también por onchange) ────────
function setCantidad(itemId, cantidad) {
  cantidad = Math.max(1, parseInt(cantidad));
  const input = document.getElementById('qty-' + itemId);
  input.value = cantidad;

  fetchCarrito('action=actualizar&carrito_id=' + itemId + '&cantidad=' + cantidad, function(d) {
    if (d.success) {
      actualizarResumen();
    } else {
      showToast(d.message || 'Error al actualizar', false);
    }
  });
}

// ── Eliminar item ─────────────────────────────────────────────
function eliminarItem(itemId) {
  if (!confirm('¿Eliminar este producto del carrito?')) return;

  const fila = document.getElementById('item-' + itemId);
  // Animación de salida inmediata
  fila.style.transition  = 'opacity .25s, transform .25s';
  fila.style.opacity     = '0';
  fila.style.transform   = 'translateX(20px)';

  fetchCarrito('action=eliminar&carrito_id=' + itemId, function(d) {
    if (d.success) {
      setTimeout(() => {
        fila.remove();
        actualizarResumen();

        // Si no quedan filas, mostrar carrito vacío
        const filas = document.querySelectorAll('[id^="item-"]');
        if (filas.length === 0) location.reload();
      }, 260);
      if (typeof updateBadge === 'function') updateBadge(d.cart_count);
    } else {
      // Revertir animación si falla
      fila.style.opacity   = '1';
      fila.style.transform = '';
      showToast('Error al eliminar', false);
    }
  });
}

// ── Vaciar carrito ────────────────────────────────────────────
function vaciarCarrito() {
  if (!confirm('¿Vaciar todo el carrito?')) return;
  fetchCarrito('action=vaciar', function(d) {
    if (d.success) location.reload();
  });
}
</script>

<?php include 'includes/footer.php'; ?>