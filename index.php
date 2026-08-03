<?php
require_once 'includes/config.php';
$pageTitle = 'Inicio';
$pdo = getDB();

// Función recursiva para contar productos (igual a productos.php)
function countCategoryProductsIndex($catId, $pdo, &$catIds = []) {
    if (empty($catIds)) {
        $catIds = [$catId];
    } else {
        $catIds[] = $catId;
    }
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE padre_id = ?");
    $stmt->execute([$catId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($children as $child) {
        countCategoryProductsIndex($child, $pdo, $catIds);
    }
    return $catIds;
}

// Obtener categorías padre con conteo recursivo
$catsRaw = $pdo->query("SELECT * FROM categorias WHERE COALESCE(padre_id,0)=0 AND nombre <> '__PROMOCIONES__' ORDER BY nombre")->fetchAll();
$categorias = [];
foreach ($catsRaw as $cat) {
    $catIds = [];
    $descendantIds = countCategoryProductsIndex($cat['id'], $pdo, $catIds);
    $placeholders = implode(',', array_fill(0, count($descendantIds), '?'));
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id IN ($placeholders) AND activo=1");
    $countStmt->execute($descendantIds);
    $cat['total'] = (int)$countStmt->fetchColumn();
    $categorias[] = $cat;
}

$destacados  = $pdo->query("SELECT p.*,c.icono FROM productos p JOIN categorias c ON p.categoria_id=c.id WHERE p.destacado=1 AND p.activo=1 AND p.nombre NOT LIKE '%mpresora%' AND p.nombre NOT LIKE '%printer%' AND p.nombre NOT LIKE '%multifuncional%' ORDER BY p.creado_en DESC LIMIT 8")->fetchAll();
$ofertas     = $pdo->query("SELECT p.*,c.icono FROM productos p JOIN categorias c ON p.categoria_id=c.id WHERE p.precio_oferta IS NOT NULL AND p.activo=1 AND p.nombre NOT LIKE '%mpresora%' AND p.nombre NOT LIKE '%printer%' ORDER BY RAND() LIMIT 4")->fetchAll();

// Productos reales para las tarjetas del hero (sin precio, solo referencia)
$heroProductos = array_slice($destacados, 0, 4);
if (count($heroProductos) < 4) {
    $faltan     = 4 - count($heroProductos);
    $idsExcluir = array_column($heroProductos, 'id');
    $sqlHero    = "SELECT p.*,c.icono FROM productos p JOIN categorias c ON p.categoria_id=c.id
                   WHERE p.activo=1" . (!empty($idsExcluir) ? " AND p.id NOT IN (" . implode(',', array_fill(0, count($idsExcluir), '?')) . ")" : "") . "
                   ORDER BY (p.stock<>'0') DESC, p.creado_en DESC LIMIT $faltan";
    $stmtHero = $pdo->prepare($sqlHero);
    $stmtHero->execute($idsExcluir);
    $heroProductos = array_merge($heroProductos, $stmtHero->fetchAll());
}

// Banners dinámicos
try {
    $banners = $pdo->query("SELECT * FROM banners WHERE activo=1 ORDER BY orden ASC")->fetchAll();
} catch(Exception $e) { $banners = []; }

// Testimonios aprobados
try {
    $testimonios = $pdo->query("SELECT * FROM testimonios WHERE aprobado=1 ORDER BY creado_en DESC LIMIT 6")->fetchAll();
} catch(Exception $e) { $testimonios = []; }

// Guardar nuevo testimonio
$testi_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_testimonio'])) {
    $tnombre  = sanitize(trim($_POST['t_nombre']  ?? ''));
    $tempresa = sanitize(trim($_POST['t_empresa'] ?? ''));
    $tcargo   = sanitize(trim($_POST['t_cargo']   ?? ''));
    $tmensaje = sanitize(trim($_POST['t_mensaje'] ?? ''));
    $testrellas = max(1, min(5, (int)($_POST['t_estrellas'] ?? 5)));
    if ($tnombre && strlen($tmensaje) >= 20) {
        try {
            $pdo->prepare("INSERT INTO testimonios (nombre,empresa,cargo,mensaje,estrellas,aprobado) VALUES (?,?,?,?,?,0)")
                ->execute([$tnombre, $tempresa, $tcargo, $tmensaje, $testrellas]);
            $testi_msg = 'success';
        } catch(Exception $e) { $testi_msg = 'error'; }
    } else {
        $testi_msg = 'validation';
    }
}

include 'includes/header.php';
?>

<?php if (!empty($banners)): ?>
<!-- BANNER DINÁMICO -->
<section class="promo-banner-wrap">
  <div class="promo-slider" id="promoSlider">
    <?php foreach($banners as $i => $b): ?>
    <div class="promo-slide <?= $i===0?'active':'' ?>"
         style="background:<?= sanitize($b['color_fondo']) ?>;">
      <div class="container">
        <div class="promo-slide-inner">
          <div class="promo-text">
            <div class="promo-badge"><i class="fas fa-tags"></i> Promoción especial</div>
            <h2 class="promo-titulo" style="color:<?= sanitize($b['color_texto']) ?>;">
              <?= sanitize($b['titulo']) ?>
            </h2>
            <?php if ($b['subtitulo']): ?>
              <p class="promo-sub" style="color:<?= sanitize($b['color_texto']) ?>;opacity:.85;">
                <?= sanitize($b['subtitulo']) ?>
              </p>
            <?php endif; ?>
            <?php if ($b['url_boton']): ?>
              <a href="<?= sanitize($b['url_boton']) ?>" class="promo-btn"
                 style="<?= $b['color_texto']==='#000000'?'background:#fff;color:#000':'background:#CEFF04;color:#000' ?>">
                <?= sanitize($b['texto_boton']) ?> <i class="fas fa-arrow-right"></i>
              </a>
            <?php endif; ?>
          </div>
          <?php if (!empty($b['imagen'])): ?>
          <div class="promo-img-wrap">
            <img src="<?= SITE_URL ?>/<?= sanitize($b['imagen']) ?>"
                 alt="<?= sanitize($b['titulo']) ?>" loading="lazy">
          </div>
          <?php else: ?>
          <div class="promo-icon-wrap">
            <i class="fas fa-tags" style="font-size:80px;opacity:.15;color:<?= sanitize($b['color_texto']) ?>;"></i>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($banners) > 1): ?>
  <div class="promo-nav">
    <button class="promo-prev" onclick="promoPrev()"><i class="fas fa-chevron-left"></i></button>
    <div class="promo-dots">
      <?php foreach($banners as $i => $b): ?>
        <span class="promo-dot <?= $i===0?'active':'' ?>" onclick="promoGoTo(<?= $i ?>)"></span>
      <?php endforeach; ?>
    </div>
    <button class="promo-next" onclick="promoNext()"><i class="fas fa-chevron-right"></i></button>
  </div>
  <?php endif; ?>
</section>
<style>
.promo-banner-wrap{position:relative;overflow:hidden;}
.promo-slider{position:relative;}
.promo-slide{display:none;padding:28px 0;transition:opacity .4s;}
.promo-slide.active{display:block;}
.promo-slide-inner{display:flex;align-items:center;justify-content:space-between;gap:24px;min-height:120px;}
.promo-text{flex:1;}
.promo-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.15);color:#fff;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;}
.promo-titulo{font-size:clamp(18px,3vw,28px);font-weight:900;line-height:1.2;margin-bottom:8px;}
.promo-sub{font-size:14px;margin-bottom:16px;max-width:520px;}
.promo-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:800;text-decoration:none;transition:all .2s;}
.promo-btn:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,.2);}
.promo-img-wrap img{max-height:100px;object-fit:contain;border-radius:8px;}
.promo-icon-wrap{flex-shrink:0;}
.promo-nav{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:12px;}
.promo-prev,.promo-next{width:30px;height:30px;border:none;background:rgba(255,255,255,.2);color:#fff;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:background .2s;}
.promo-prev:hover,.promo-next:hover{background:rgba(255,255,255,.4);}
.promo-dots{display:flex;gap:6px;}
.promo-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.4);cursor:pointer;transition:background .2s;}
.promo-dot.active{background:#D7E022;}
</style>
<script>
(function(){
  let cur=0;
  const slides=document.querySelectorAll('.promo-slide');
  const dots=document.querySelectorAll('.promo-dot');
  const total=slides.length;
  if(total<=1)return;
  let timer=setInterval(()=>promoNext(),5000);
  window.promoGoTo=function(n){
    slides[cur].classList.remove('active');
    dots[cur]?.classList.remove('active');
    cur=(n+total)%total;
    slides[cur].classList.add('active');
    dots[cur]?.classList.add('active');
    clearInterval(timer);
    timer=setInterval(()=>promoNext(),5000);
  };
  window.promoNext=()=>promoGoTo(cur+1);
  window.promoPrev=()=>promoGoTo(cur-1);
})();
</script>
<?php endif; ?>

<!-- HERO -->
<section class="hero">

  </div>
  <div class="container">
    <div>
      <div class="hero-badge"><i class="fas fa-star"></i> #1 en Seguridad Electrónica en Perú</div>
      <h1>Protege lo que más<br>importa con <span class="hl">tecnología</span><br>de primer nivel</h1>
      <p>Cámaras IP, routers empresariales, alarmas y estabilizadores UPS de las mejores marcas. Envío a todo el Perú.</p>      <div class="hero-btns">
        <a href="<?= SITE_URL ?>/productos.php" class="btn-main"><i class="fas fa-shopping-bag"></i> Ver Catálogo</a>
        <a href="https://wa.me/51950923109" target="_blank" class="btn-wsp"><i class="fab fa-whatsapp"></i> Asesoría Gratis</a>
      </div>
      <div class="hero-stats">
        <div class="stat">
          <div class="num">500+</div>
          <div class="lbl">Productos</div>
        </div>
        <div class="stat">
          <div class="num">50+</div>
          <div class="lbl">Marcas</div>
        </div>
        <div class="stat">
          <div class="num">10K+</div>
          <div class="lbl">Clientes</div>
        </div>
        <div class="stat">
          <div class="num">3</div>
          <div class="lbl">Sedes Lima</div>
        </div>
      </div>
    </div>
    <div class="hero-visual">
          <?php foreach ($heroProductos as $p): ?>
        <a class="hcard" href="<?= SITE_URL ?>/producto.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;">
          <div class="hcard-icon">
            <?php if (!empty($p['imagen'])): ?>
              <img src="<?= SITE_URL ?>/<?= sanitize($p['imagen']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
              <i class="fas <?= sanitize($p['icono']) ?>"></i>
            <?php endif; ?>
          </div>
          <div>
            <div class="hcard-title"><?= sanitize($p['nombre']) ?></div>
            <div class="hcard-desc"><?= sanitize($p['marca']) ?><?= !empty($p['modelo']) ? ' · ' . sanitize($p['modelo']) : '' ?></div>
          </div>
          <div class="hcard-price">
            <?php if ($p['stock'] && $p['stock'] !== '0'): ?>
              <div class="from" style="color:#2e7d32;">Disponible</div>
            <?php else: ?>
              <div class="from" style="color:#b00020;">Agotado</div>
            <?php endif; ?>
            <i class="fas fa-arrow-right"></i>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- BENEFITS -->
<div class="benefits">
  <div class="container">
    <?php foreach ([['fas fa-shipping-fast', 'Delivery a todo el Perú', 'Lima 24h · Provincias 48-72h'], ['fas fa-shield-alt', 'Garantía Oficial', 'Todos los productos certificados'], ['fas fa-headset', 'Soporte Técnico 24/7', 'Asesoría especializada gratis'], ['fas fa-mobile-alt', 'Yape · Plin · Tarjeta', '6 métodos de pago disponibles']] as [$ico, $ttl, $desc]): ?>
      <div class="benefit">
        <div class="benefit-icon"><i class="<?= $ico ?>"></i></div>
        <div>
          <div class="benefit-ttl"><?= $ttl ?></div>
          <div class="benefit-desc"><?= $desc ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- BRANDS -->
<div class="brands">
  <div class="container">
    <div class="brands-inner">
      <span class="brands-lbl">Marcas oficiales:</span>
      <div class="brands-logos-wrap">
        <?php
        $marcas = [
          ['Hytera',         SITE_URL . '/assets/img/marcas/Hytera.png'],
          ['IMOU',           SITE_URL . '/assets/img/marcas/Imou.png'],
          ['TP-Link',        SITE_URL . '/assets/img/marcas/Tplink.png'],
          ['Simplex',        SITE_URL . '/assets/img/marcas/Simplex.png'],
          ['LG',             SITE_URL . '/assets/img/marcas/LG.png'],
          ['Ubiquiti',       SITE_URL . '/assets/img/marcas/Ubiquiti.png'],
          ['Seagate',        SITE_URL . '/assets/img/marcas/Seagate.png'],
          ['Dahua',          SITE_URL . '/assets/img/marcas/Dahua.png'],
          ['Motorola',       SITE_URL . '/assets/img/marcas/Motorola.png'],
          ['Hikvision',      SITE_URL . '/assets/img/marcas/Hikvision.png'],
          ['Conduit',        SITE_URL . '/assets/img/marcas/Conduid.png'],
          ['Western Digital',SITE_URL . '/assets/img/marcas/Wester.png'],
          ['Hagroy',         SITE_URL . '/assets/img/marcas/Hagroy.png'],
          ['Ezviz',          SITE_URL . '/assets/img/marcas/Ezviz.png'],
          ['Ravel',          SITE_URL . '/assets/img/marcas/Ravel.png'],
          ['Toshiba',        SITE_URL . '/assets/img/marcas/Toshiba.png'],
          ['Forza',          SITE_URL . '/assets/img/marcas/Forza.png'],
          ['Dixon',          SITE_URL . '/assets/img/marcas/Dixon.png'],
          ['Logitech',       SITE_URL . '/assets/img/marcas/Logitech.png'],
          ['Honeywell',      SITE_URL . '/assets/img/marcas/Honeywell.png'],
          ['Samsung',        SITE_URL . '/assets/img/marcas/Samsung.png'],
          ['Kingston',       SITE_URL . '/assets/img/marcas/Kingston.png'],
        ];
        foreach ($marcas as [$nombre, $logo]):
        ?>
        <div class="brand-logo-item" title="<?= $nombre ?>">
          <img src="<?= $logo ?>" alt="<?= $nombre ?>" loading="lazy">
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- CATEGORIAS -->
<section class="section">
  <div class="container">
    <div class="sec-head">
      <span class="sec-badge">¿Qué buscas?</span>
      <h2>Nuestras Categorías</h2>
      <p>Soluciones tecnológicas para tu hogar o empresa</p>
    </div>
    <div class="cat-grid">
      <?php foreach ($categorias as $cat): ?>
        <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $cat['id'] ?>" class="cat-card">
          <div class="cat-card-icon"><i class="fas <?= sanitize($cat['icono']) ?>"></i></div>
          <h3><?= sanitize($cat['nombre']) ?></h3>
          <p><?= sanitize($cat['descripcion'] ?? '') ?></p>
          <span class="cat-count"><?= $cat['total'] ?> productos</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- DESTACADOS -->
<section class="section section-alt">
  <div class="container">
    <div class="sec-head">
      <span class="sec-badge">⭐ Más vendidos</span>
      <h2>Productos Destacados</h2>
      <p>Los equipos favoritos de nuestros clientes</p>
    </div>
    <?php if (empty($destacados)): ?>
      <p style="text-align:center;color:var(--gray);padding:50px;background:var(--dark3);border-radius:14px;border:1.5px dashed var(--border);">
        No hay destacados aún. <a href="<?= SITE_URL ?>/admin/productos.php" style="color:var(--primary);">Configura en el Admin →</a>
      </p>
    <?php else: ?>
      <div class="prod-grid">
        <?php foreach ($destacados as $p):
          $desc = $p['precio_oferta'] ? round((1 - $p['precio_oferta'] / $p['precio']) * 100) : 0;
        ?>
          <div class="prod-card">
            <a class="prod-card-link" href="<?= SITE_URL ?>/producto.php?id=<?= $p['id'] ?>"></a>
            <div class="prod-img">
              <?php if ($desc > 0): ?><span class="badge-off">-<?= $desc ?>%</span><?php endif; ?>
              <span class="badge-hot">★ DEST.</span>
              <?php if (!empty($p['imagen'])): ?>
                <img src="<?= SITE_URL ?>/<?= sanitize($p['imagen']) ?>" alt="<?= sanitize($p['nombre']) ?>" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <div class="prod-ph"><i class="fas <?= sanitize($p['icono']) ?>"></i><span><?= sanitize($p['marca']) ?></span></div>
              <?php endif; ?>
            </div>
            <div class="prod-body">
              <div class="prod-brand"><?= sanitize($p['marca']) ?></div>
              <div class="prod-name"><a href="<?= SITE_URL ?>/producto.php?id=<?= $p['id'] ?>"><?= sanitize($p['nombre']) ?></a></div>
              <?= renderPrecioCarrito($p, $p['precio_oferta'] ?? $p['precio'], $desc, $p['id']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div style="text-align:center;margin-top:36px;">
        <a href="<?= SITE_URL ?>/productos.php" class="btn-main"><i class="fas fa-th-large"></i> Ver todos los productos</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- OFERTAS -->
<?php if (!empty($ofertas)): ?>
  <section class="oferta-banner">
    <div class="container">
      <div class="oferta-inner">
        <div>
          <div class="oferta-tag"><i class="fas fa-tags"></i> Ofertas especiales</div>
          <h2>Precios<br>Increíbles</h2>
          <p>Solo por tiempo<br>limitado. No te quedes sin los tuyos.</p>
          <a href="<?= SITE_URL ?>/productos.php?orden=oferta" class="btn-main" style="display:inline-flex;margin-top:20px;font-size:14px;padding:11px 22px;">
            <i class="fas fa-bolt"></i> Ver todas las ofertas
          </a>
        </div>
        <div class="oferta-grid">
          <?php foreach ($ofertas as $o):
            $desc = round((1 - $o['precio_oferta'] / $o['precio']) * 100);
          ?>
            <div class="ocard" style="position:relative;cursor:pointer;">
              <a style="position:absolute;inset:0;z-index:1;" href="<?= SITE_URL ?>/producto.php?id=<?= $o['id'] ?>"></a>
              <?php if (!empty($o['imagen'])): ?>
                <img src="<?= SITE_URL ?>/<?= sanitize($o['imagen']) ?>" alt="<?= sanitize($o['nombre']) ?>" loading="lazy" style="width:80px;height:80px;object-fit:contain;">
              <?php else: ?>
                <div class="ocard-icon"><i class="fas <?= sanitize($o['icono']) ?>"></i></div>
              <?php endif; ?>
              <div class="ocard-brand"><?= sanitize($o['marca']) ?></div>
              <div class="ocard-name"><?= sanitize($o['nombre']) ?></div>
              <?php if (isLoggedIn()): ?>
                <div class="ocard-old"><?= formatPrice($o['precio']) ?></div>
                <div class="ocard-new"><?= formatPrice($o['precio_oferta']) ?><span class="ocard-pct">-<?= $desc ?>%</span></div>
                <button class="btn-oferta btn-add-cart" style="position:relative;z-index:2;" data-id="<?= $o['id'] ?>"><i class="fas fa-cart-plus"></i> Agregar</button>
              <?php else: ?>
                <div class="ocard-new"><span class="ocard-pct">-<?= $desc ?>%</span></div>
                <a href="<?= SITE_URL ?>/login.php?redirect=<?= urlencode($_SERVER['REQUEST_URI'] ?? '/') ?>" class="btn-oferta" style="position:relative;z-index:2;display:flex;align-items:center;justify-content:center;gap:6px;">
                  <i class="fas fa-lock"></i> Ver precio
                </a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<!-- INFO CARDS -->
<section class="info-section">
  <div class="container">
    <div class="info-grid">
      <div class="icard yellow">
        <i class="fas fa-tools"></i>
        <h3>Instalación y Configuración</h3>
        <p>Técnicos certificados para instalar y configurar tus equipos en domicilio o empresa, en Lima y provincias.</p>
        <a href="<?= SITE_URL ?>/contacto.php"><i class="fas fa-arrow-right"></i> Solicitar servicio</a>
      </div>
      <div class="icard dark">
        <i class="fas fa-store"></i>
        <h3>3 Tiendas en Lima</h3>
        <p>Visítanos en Centro de Lima, La Molina o Ate. Recoge sin costo adicional de lunes a sábado.</p>
        <a href="#sedes"><i class="fas fa-map-marker-alt"></i> Ver sedes</a>
      </div>
      <div class="icard black">
        <i class="fab fa-whatsapp"></i>
        <h3>Asesoría por WhatsApp</h3>
        <p>¿No sabes qué equipo elegir? Nuestros expertos te guían de forma personalizada y totalmente gratuita.</p>
        <a href="https://wa.me/51950923109" target="_blank"><i class="fab fa-whatsapp"></i> +51 950 923 109</a>
      </div>
    </div>
  </div>
</section>

<!-- TESTIMONIOS -->
<?php if (!empty($testimonios)): ?>
<section class="testimonios-section">
  <div class="container">
    <div class="sec-head">
      <span class="sec-badge"><i class="fas fa-star"></i> Opiniones reales</span>
      <h2>Lo que dicen nuestros clientes</h2>
      <p>Miles de clientes confían en IP Tecnología para proteger lo que más importa</p>
    </div>
    <div class="testi-grid">
      <?php foreach($testimonios as $t): ?>
      <div class="testi-card">
        <div class="testi-stars">
          <?php for($s=1;$s<=5;$s++): ?>
            <i class="fas fa-star" style="color:<?= $s<=$t['estrellas']?'#D7E022':'#555' ?>;font-size:14px;"></i>
          <?php endfor; ?>
        </div>
        <p class="testi-msg">"<?= sanitize($t['mensaje']) ?>"</p>
        <div class="testi-author">
          <div class="testi-avatar"><?= strtoupper(substr(sanitize($t['nombre']),0,1)) ?></div>
          <div>
            <div class="testi-name"><?= sanitize($t['nombre']) ?></div>
            <?php if($t['cargo'] || $t['empresa']): ?>
              <div class="testi-role">
                <?= $t['cargo'] ? sanitize($t['cargo']) : '' ?>
                <?= ($t['cargo'] && $t['empresa']) ? ' · ' : '' ?>
                <?= $t['empresa'] ? sanitize($t['empresa']) : '' ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Formulario para dejar reseña -->
    <div class="testi-form-wrap">
      <h3><i class="fas fa-pen-alt"></i> Deja tu reseña</h3>
      <?php if($testi_msg==='success'): ?>
        <div class="alert alert-success" style="margin-bottom:16px;">
          <i class="fas fa-check-circle"></i> ¡Gracias por tu reseña! Será publicada tras revisión.
        </div>
      <?php elseif($testi_msg==='validation'): ?>
        <div class="alert alert-error" style="margin-bottom:16px;">
          <i class="fas fa-times-circle"></i> Por favor completa tu nombre y escribe al menos 20 caracteres.
        </div>
      <?php elseif($testi_msg==='error'): ?>
        <div class="alert alert-error" style="margin-bottom:16px;">
          <i class="fas fa-times-circle"></i> Ocurrió un error. Intenta nuevamente.
        </div>
      <?php endif; ?>
      <form method="POST" class="testi-form">
        <div class="testi-form-row">
          <div class="testi-form-group">
            <label>Tu nombre *</label>
            <input type="text" name="t_nombre" placeholder="Ej: Juan Pérez" required maxlength="120">
          </div>
          <div class="testi-form-group">
            <label>Empresa (opcional)</label>
            <input type="text" name="t_empresa" placeholder="Ej: Mi Empresa SAC" maxlength="120">
          </div>
          <div class="testi-form-group">
            <label>Cargo (opcional)</label>
            <input type="text" name="t_cargo" placeholder="Ej: Gerente de TI" maxlength="100">
          </div>
        </div>
        <div class="testi-form-group" style="margin-bottom:12px;">
          <label>Calificación *</label>
          <div class="star-rating" id="starRating">
            <?php for($s=1;$s<=5;$s++): ?>
              <i class="fas fa-star" data-val="<?= $s ?>" style="cursor:pointer;font-size:24px;color:#D7E022;"></i>
            <?php endfor; ?>
            <input type="hidden" name="t_estrellas" id="starVal" value="5">
          </div>
        </div>
        <div class="testi-form-group">
          <label>Tu opinión * <span style="color:var(--gray);font-size:11px;">(mínimo 20 caracteres)</span></label>
          <textarea name="t_mensaje" rows="3" placeholder="Cuéntanos tu experiencia con IP Tecnología..." required minlength="20" maxlength="800"></textarea>
        </div>
        <button type="submit" name="enviar_testimonio" class="btn-main" style="margin-top:4px;">
          <i class="fas fa-paper-plane"></i> Enviar reseña
        </button>
      </form>
    </div>
  </div>
</section>
<style>
.testimonios-section{padding:60px 0;background:var(--dark2,#0d0d0d);}
.testi-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px;margin-bottom:40px;}
.testi-card{background:var(--dark3,#1a1a1a);border:1.5px solid var(--border,#2a2a2a);border-radius:14px;padding:22px;display:flex;flex-direction:column;gap:12px;transition:border-color .2s;}
.testi-card:hover{border-color:#D7E022;}
.testi-msg{color:var(--gray2,#ccc);font-size:14px;line-height:1.7;flex:1;font-style:italic;}
.testi-author{display:flex;align-items:center;gap:12px;margin-top:4px;}
.testi-avatar{width:42px;height:42px;border-radius:50%;background:#D7E022;color:#000;font-weight:900;font-size:18px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.testi-name{font-size:14px;font-weight:700;color:var(--white,#fff);}
.testi-role{font-size:11px;color:var(--gray,#888);margin-top:2px;}
.testi-form-wrap{background:var(--dark3,#1a1a1a);border:1.5px solid var(--border,#2a2a2a);border-radius:14px;padding:28px;max-width:760px;margin:0 auto;}
.testi-form-wrap h3{font-size:18px;font-weight:800;color:var(--white,#fff);margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.testi-form-wrap h3 i{color:#D7E022;}
.testi-form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:12px;}
.testi-form-group{display:flex;flex-direction:column;gap:5px;}
.testi-form-group label{font-size:12px;font-weight:700;color:var(--gray,#888);text-transform:uppercase;letter-spacing:.3px;}
.testi-form-group input,.testi-form-group textarea{background:var(--dark2,#111);border:1.5px solid var(--border,#2a2a2a);border-radius:8px;padding:9px 12px;color:var(--white,#fff);font-size:14px;transition:border-color .2s;resize:vertical;}
.testi-form-group input:focus,.testi-form-group textarea:focus{border-color:#D7E022;outline:none;}
.star-rating{display:flex;gap:4px;}
.star-rating .fa-star{transition:color .15s;}
</style>
<script>
(function(){
  const stars = document.querySelectorAll('#starRating .fa-star');
  const inp   = document.getElementById('starVal');
  if (!stars.length) return;
  function render(n){
    stars.forEach((s,i)=>{ s.style.color = i<n ? '#D7E022' : '#444'; });
  }
  stars.forEach((s,i)=>{
    s.addEventListener('mouseover',()=>render(i+1));
    s.addEventListener('mouseout', ()=>render(parseInt(inp.value)));
    s.addEventListener('click',   ()=>{ inp.value=i+1; render(i+1); });
  });
})();
</script>
<?php endif; ?>

<!-- SEDES -->
<section class="sedes-section" id="sedes">
  <div class="container">
    <div class="sec-head">
      <span class="sec-badge"><i class="fas fa-map-marker-alt"></i> Encuéntranos</span>
      <h2>Nuestras 3 Sedes en Lima</h2>
      <p>Atención presencial de Lunes a Sábado, 9am a 7pm</p>
    </div>
    <div class="sedes-grid">

      <div class="sede-card">
        <iframe class="sede-map" src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d243.8634457454953!2d-77.02584925421654!3d-12.056237337699853!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c50da3092917%3A0x69243f0d60417c04!2sIP%20TECNOLOGIA%20PERU!5e0!3m2!1ses-419!2sus!4v1772745424249!5m2!1ses-419!2sus" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="sede-body">
          <span class="sede-badge"><i class="fas fa-star"></i> Sede Lima</span>
          <h3>Lima</h3>
          <div class="sede-info">
            <div class="sede-row"><i class="fas fa-map-marker-alt"></i><span>Jr. Paruro Nº 1322 - Sótano Tda - S112</span></div>
            <div class="sede-row"><i class="fas fa-phone"></i><span>+51 950 923 109</span></div>
          </div>
        </div>
        <div class="sede-footer">
          <a href="https://maps.google.com/?q=Jr.+Paruro+1322,+Lima,+Peru" target="_blank" class="btn-sede-maps"><i class="fas fa-directions"></i> Cómo llegar</a>
          <a href="https://wa.me/51950923109?text=Hola, quiero info de la sede Lima" target="_blank" class="btn-sede-wsp"><i class="fab fa-whatsapp"></i></a>
        </div>
      </div>


      <div class="sede-card">
        <iframe class="sede-map" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d243.853401936933!2d-76.93873529877933!3d-12.067280983452005!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c7906d9cdf45%3A0x7b5d1bfea47e4153!2sIP%20TECNOLOGIA%20PERU!5e0!3m2!1ses-419!2spe!4v1775775669963!5m2!1ses-419!2spe" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="sede-body">
          <span class="sede-badge alt"><i class="fas fa-store"></i> Sede La Molina</span>
          <h3>La Molina</h3>
          <div class="sede-info">
            <div class="sede-row"><i class="fas fa-map-marker-alt"></i><span>Av. Melgarejo Nº 595</span></div>
            <div class="sede-row"><i class="fas fa-phone"></i><span>+51 950 923 109</span></div>
          </div>
        </div>
        <div class="sede-footer">
          <a href="https://maps.google.com/?q=Av.+Melgarejo+595,+La+Molina,+Lima,+Peru" target="_blank" class="btn-sede-maps"><i class="fas fa-directions"></i> Cómo llegar</a>
          <a href="https://wa.me/51950923109?text=Hola, quiero info de la sede La Molina" target="_blank" class="btn-sede-wsp"><i class="fab fa-whatsapp"></i></a>
        </div>
      </div>

      <div class="sede-card">
        <iframe class="sede-map" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d6562.786114756766!2d-76.9242961985887!3d-12.025890820728838!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x9105c55d7687c77f%3A0x7b128196a9ed17f7!2sIP%20TECNOLOGIA%20PERU!5e0!3m2!1ses-419!2spe!4v1772745002448!5m2!1ses-419!2spe" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="sede-body">
          <span class="sede-badge alt"><i class="fas fa-store"></i> Sede Ate</span>
          <h3>Ate - Plaza Vitarte</h3>
          <div class="sede-info">
            <div class="sede-row"><i class="fas fa-map-marker-alt"></i><span>Carretera Central KM 7.5</span></div>
            <div class="sede-row"><i class="fas fa-store"></i><span>C.C. Plaza Vitarte, Block F Tda. 304</span></div>
            <div class="sede-row"><i class="fas fa-phone"></i><span>+51 950 923 109</span></div>
          </div>
        </div>
        <div class="sede-footer">
          <a href="https://maps.google.com/?q=Carretera+Central+KM+7.5,+Plaza+Vitarte,+Ate,+Lima,+Peru" target="_blank" class="btn-sede-maps"><i class="fas fa-directions"></i> Cómo llegar</a>
          <a href="https://wa.me/51950923109?text=Hola, quiero info de la sede Ate" target="_blank" class="btn-sede-wsp"><i class="fab fa-whatsapp"></i></a>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- PAYMENT -->
<div class="pay-section">
  <div class="container">
    <div class="pay-inner">
      <div class="pay-title">Métodos de Pago aceptados<span>Transacciones 100% seguras</span></div>
      <div class="pay-methods">
        <div class="pmethod"><i class="fab fa-cc-visa" style="color:#1a1f71;"></i> Visa</div>
        <div class="pmethod"><i class="fab fa-cc-mastercard" style="color:#eb001b;"></i> Mastercard</div>
        <div class="pmethod pmethod-yape">
          <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8e/Yape_logo.svg/320px-Yape_logo.svg.png"
               alt="Yape" style="height:20px;object-fit:contain;"
               onerror="this.outerHTML='<i class=\'fas fa-mobile-alt\' style=\'color:#6b2d8c;\'></i>'">
          Yape
        </div>
        <div class="pmethod"><i class="fas fa-mobile-alt" style="color:var(--primary);"></i> Plin</div>
        <div class="pmethod"><i class="fas fa-university" style="color:var(--primary);"></i> BCP</div>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>