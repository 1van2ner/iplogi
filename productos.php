<?php
require_once 'includes/config.php';
$pdo = getDB();

$categoriaId = (int)($_GET['categoria'] ?? 0);
$q           = sanitize($_GET['q'] ?? '');
$orden       = sanitize($_GET['orden'] ?? 'recientes');
$marcaFilt   = sanitize($_GET['marca'] ?? '');
$precioMin   = (float)($_GET['pmin'] ?? 0);
$precioMax   = (float)($_GET['pmax'] ?? 99999);
$vaFilt      = (int)($_GET['va'] ?? 0);
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina   = 12;
$offset      = ($pagina - 1) * $porPagina;
$scrollInf   = ($_GET['scroll'] ?? '0') === '1';
$ajaxLoad    = ($_GET['ajax']   ?? '0') === '1';

// Función recursiva para contar productos en una categoría y sus subcategorías
function countCategoryProducts($catId, $pdo, &$catIds = []) {
    if (empty($catIds)) {
        $catIds = [$catId];
    } else {
        $catIds[] = $catId;
    }
    $stmt = $pdo->prepare("SELECT id FROM categorias WHERE padre_id = ?");
    $stmt->execute([$catId]);
    $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($children as $child) {
        countCategoryProducts($child, $pdo, $catIds);
    }
    return $catIds;
}

// Conteo de categorías
$cats = [];
$catStmt = $pdo->query("SELECT id, nombre, descripcion, icono, padre_id FROM categorias ORDER BY COALESCE(padre_id, id), padre_id IS NOT NULL, nombre");
$allCats = $catStmt->fetchAll();

foreach($allCats as $c) {
    $catIds = [];
    $descendantIds = countCategoryProducts($c['id'], $pdo, $catIds);
    $placeholders = implode(',', array_fill(0, count($descendantIds), '?'));
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE categoria_id IN ($placeholders) AND activo=1");
    $countStmt->execute($descendantIds);
    $c['total'] = (int)$countStmt->fetchColumn();
    $cats[] = $c;
}

// Construir árbol de categorías
$catById2 = [];
foreach ($cats as $c) { $catById2[$c['id']] = $c; $catById2[$c['id']]['children'] = []; }
$catsRaiz = [];
foreach ($catById2 as $id => &$c2) {
    if (is_null($c2['padre_id'])) { $catsRaiz[] = &$c2; }
    elseif (isset($catById2[$c2['padre_id']])) { $catById2[$c2['padre_id']]['children'][] = &$c2; }
    else { $catsRaiz[] = &$c2; }
}
unset($c2);

// Crear tabla de marcas_oficiales si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS marcas_oficiales (id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL UNIQUE, creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
    $checkMarcas = $pdo->query("SELECT COUNT(*) FROM marcas_oficiales")->fetchColumn();
    if ($checkMarcas == 0) {
        $stmt = $pdo->prepare("INSERT INTO marcas_oficiales (nombre) VALUES (?)");
        foreach(['Hytera','IMOU','TP-Link TAPO','Simplex','LG','UBIQUITI','SEAGATE','Dahua','Motorola','HIKVISION','Conduit','Western Digital','Hagroy','EZVIZ','Ravel','TOSHIBA','Forza','DIXON','Logitech','Honeywell','Samsung','Kingston'] as $m) {
            $stmt->execute([$m]);
        }
    }
} catch(Exception $e) {}

// Filtros base
$baseWhere = ['p.activo = 1'];
$baseParams = [];
if ($q)           { $baseWhere[] = '(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)'; $baseParams = array_merge($baseParams,["%$q%","%$q%","%$q%"]); }
if ($marcaFilt)   { $baseWhere[] = 'p.marca = ?'; $baseParams[] = $marcaFilt; }
if ($precioMin)   { $baseWhere[] = 'COALESCE(p.precio_oferta,p.precio) >= ?'; $baseParams[] = $precioMin; }
if ($precioMax < 99999) { $baseWhere[] = 'COALESCE(p.precio_oferta,p.precio) <= ?'; $baseParams[] = $precioMax; }
if ($vaFilt > 0) { $baseWhere[] = 'p.potencia_va = ?'; $baseParams[] = $vaFilt; }
$baseWhereSQL = implode(' AND ', $baseWhere);

// Obtener marcas oficiales con conteo dinámico
try {
    $marcasWhere = ['p.activo = 1'];
    $marcasParams = [];
    if ($q)           { $marcasWhere[] = '(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)'; $marcasParams = array_merge($marcasParams,["%$q%","%$q%","%$q%"]); }
    if ($precioMin)   { $marcasWhere[] = 'COALESCE(p.precio_oferta,p.precio) >= ?'; $marcasParams[] = $precioMin; }
    if ($precioMax < 99999) { $marcasWhere[] = 'COALESCE(p.precio_oferta,p.precio) <= ?'; $marcasParams[] = $precioMax; }
    if ($vaFilt > 0) { $marcasWhere[] = 'p.potencia_va = ?'; $marcasParams[] = $vaFilt; }
    $marcasWhereSQL = implode(' AND ', $marcasWhere);
    $marcasStmt = $pdo->prepare("SELECT mo.nombre, COUNT(p.id) as total FROM marcas_oficiales mo LEFT JOIN productos p ON p.marca = mo.nombre AND ($marcasWhereSQL) GROUP BY mo.id HAVING total > 0 ORDER BY mo.nombre");
    $marcasStmt->execute($marcasParams);
    $marcasRes = $marcasStmt->fetchAll();
    $marcas = array_column($marcasRes, 'nombre');
} catch(Exception $e) {
    $marcasRes = [];
    $marcas = [];
}

try { $vasRaw = $pdo->query("SELECT DISTINCT potencia_va FROM productos WHERE activo=1 AND potencia_va IS NOT NULL AND potencia_va>0 ORDER BY potencia_va ASC")->fetchAll(PDO::FETCH_COLUMN); }
catch(Exception $e) { $vasRaw = []; }

$where  = ['p.activo = 1'];
$params = [];
if ($categoriaId) {
    $todosIds = [$categoriaId];
    $hijos1 = $pdo->prepare("SELECT id FROM categorias WHERE padre_id = ?");
    $hijos1->execute([$categoriaId]);
    $idsHijos = $hijos1->fetchAll(PDO::FETCH_COLUMN);
    foreach ($idsHijos as $hijo) {
        $todosIds[] = $hijo;
        $hijos2 = $pdo->prepare("SELECT id FROM categorias WHERE padre_id = ?");
        $hijos2->execute([$hijo]);
        foreach ($hijos2->fetchAll(PDO::FETCH_COLUMN) as $nieto) {
            $todosIds[] = $nieto;
        }
    }
    $todosIds = array_unique($todosIds);
    $placeholders = implode(',', array_fill(0, count($todosIds), '?'));
    $where[]  = "p.categoria_id IN ($placeholders)";
    $params   = array_merge($params, $todosIds);
}
if ($q)           { $where[] = '(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)'; $params = array_merge($params,["%$q%","%$q%","%$q%"]); }
if ($marcaFilt)   { $where[] = 'p.marca = ?'; $params[] = $marcaFilt; }
if ($precioMin)   { $where[] = 'COALESCE(p.precio_oferta,p.precio) >= ?'; $params[] = $precioMin; }
if ($precioMax < 99999) { $where[] = 'COALESCE(p.precio_oferta,p.precio) <= ?'; $params[] = $precioMax; }
if ($vaFilt > 0) { try { $where[] = 'p.potencia_va = ?'; $params[] = $vaFilt; } catch(Exception $e){} }

$orderSQL = match($orden) {
    'precio_asc'   => 'COALESCE(p.precio_oferta,p.precio) ASC',
    'precio_desc'  => 'COALESCE(p.precio_oferta,p.precio) DESC',
    'nombre'       => 'p.nombre ASC',
    'oferta'       => 'p.precio_oferta IS NULL ASC, descuento DESC',
    'mas_vendidos' => 'COALESCE(p.total_vendido,0) DESC',
    'relevancia'   => '(p.destacado*3+(p.precio_oferta IS NOT NULL)*2+(p.stock>0)) DESC, p.creado_en DESC',
    default        => 'p.creado_en DESC'
};
$whereSQL = 'WHERE '.implode(' AND ',$where);

$cs = $pdo->prepare("SELECT COUNT(*) FROM productos p $whereSQL"); $cs->execute($params);
$total        = (int)$cs->fetchColumn();
$totalPaginas = ceil($total/$porPagina);

try {
    $s = $pdo->prepare("SELECT p.*,c.icono,c.nombre as cat_nombre,COALESCE(p.total_vendido,0) as total_vendido,
        CASE WHEN p.precio_oferta IS NOT NULL THEN ROUND((1-p.precio_oferta/p.precio)*100) ELSE 0 END as descuento
        FROM productos p JOIN categorias c ON p.categoria_id=c.id $whereSQL ORDER BY $orderSQL LIMIT $porPagina OFFSET $offset");
    $s->execute($params); $productos = $s->fetchAll();
} catch(Exception $e) {
    $orderSQL2 = ($orden==='mas_vendidos'||$orden==='relevancia') ? 'p.creado_en DESC' : $orderSQL;
    $s = $pdo->prepare("SELECT p.*,c.icono,c.nombre as cat_nombre,0 as total_vendido,
        CASE WHEN p.precio_oferta IS NOT NULL THEN ROUND((1-p.precio_oferta/p.precio)*100) ELSE 0 END as descuento
        FROM productos p JOIN categorias c ON p.categoria_id=c.id $whereSQL ORDER BY $orderSQL2 LIMIT $porPagina OFFSET $offset");
    $s->execute($params); $productos = $s->fetchAll();
}

if ($ajaxLoad) {
    header('Content-Type: application/json');
    ob_start();
    foreach($productos as $p) {
        $precio = $p['precio_oferta'] ?? $p['precio'];
        $desc   = $p['descuento'];
        $msv    = ($p['total_vendido'] ?? 0) >= 5;
        $img2   = !empty($p['imagen2']) ? $p['imagen2'] : '';
        echo '<div class="prod-card" data-id="'.$p['id'].'">';
        echo '<a class="prod-card-link" href="'.SITE_URL.'/producto.php?id='.$p['id'].'"></a>';
        echo '<div class="prod-img'.($img2?' has-hover':'').'">';
        if($desc) echo '<span class="badge-desc">'.$desc.'% OFF</span>';
        if($msv) echo '<span class="badge-hot" style="background:#e53935;color:#fff;">&#128293; MÁS VENDIDO</span>';
        elseif($p['destacado']) echo '<span class="badge-hot">&#9733; DEST.</span>';
        if (!empty($p['canje_puntos']) && (int)$p['canje_puntos'] > 0) {
            echo '<span class="badge-canje">Canjea '.number_format((int)$p['canje_puntos']).' pts</span>';
        }
        if(!empty($p['imagen'])) {
            echo '<img class="img-principal" src="'.SITE_URL.'/'.htmlspecialchars($p['imagen']).'" alt="'.htmlspecialchars($p['nombre']).'" loading="lazy">';
            if($img2) echo '<img class="img-hover" src="'.SITE_URL.'/'.$img2.'" alt="" loading="lazy">';
        } else {
            echo '<div class="prod-ph"><i class="fas '.htmlspecialchars($p['icono']).'"></i><span>'.htmlspecialchars($p['marca']).'</span></div>';
        }
        if (isLoggedIn()) {
            echo '<button class="btn-comparar" data-id="'.$p['id'].'" data-nombre="'.htmlspecialchars($p['nombre']).'" data-precio="'.formatPrice($precio).'" data-img="'.(!empty($p['imagen'])?SITE_URL.'/'.$p['imagen']:'').'"><i class="fas fa-exchange-alt"></i></button>';
        }
        echo '</div><div class="prod-body">';
        echo '<div class="prod-brand">'.htmlspecialchars($p['marca']).'</div>';
        echo '<div class="prod-name"><a href="'.SITE_URL.'/producto.php?id='.$p['id'].'">'.htmlspecialchars($p['nombre']).'</a></div>';
        if(($p['potencia_va']??0)>0) echo '<div style="font-size:11px;color:var(--primary);font-weight:700;margin-bottom:2px;"><i class="fas fa-bolt"></i> '.(int)$p['potencia_va'].' VA</div>';
        echo renderPrecioCarrito($p, $precio, $desc, $p['id']);
        echo '</div></div>';
    }
    $html = ob_get_clean();
    echo json_encode(['html'=>$html,'hayMas'=>$pagina<$totalPaginas,'total'=>$total]);
    exit;
}

$catActual = null;
if ($categoriaId) {
    $cs2 = $pdo->prepare("SELECT * FROM categorias WHERE id=?"); $cs2->execute([$categoriaId]);
    $catActual = $cs2->fetch();
}
$pageTitle = $catActual ? $catActual['nombre'] : ($q ? "Búsqueda: $q" : 'Productos');
include 'includes/header.php';
?>
<style>
/* ── Badges ───────────────────────────────────────────────── */
.badge-desc {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #e53935;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 5px;
    z-index: 3;
    pointer-events: none;
}
.badge-hot {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #CEFF04;
    color: #000;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 5px;
    z-index: 3;
    pointer-events: none;
}

.badge-canje {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(255,255,255,0.92);
    color: #333;
    font-size: 11px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 999px;
    border: 1px solid rgba(0,0,0,0.08);
    z-index: 3;
    pointer-events: none;
}

/* ── Contenedor imagen ────────────────────────────────────── */
.prod-card {
    overflow: visible !important;
    position: relative;
}

.prod-img {
    position: relative !important;
    overflow: hidden !important;
    height: 200px !important;
    background: #f5f5f7 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 12px 12px 0 0 !important;
}

/* Imagen principal */
.prod-img .img-principal {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center !important;
    padding: 0 !important;
    font-size: 0 !important;
    color: transparent !important;
}

/* Imagen hover */
.prod-img .img-hover {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important;
    object-position: center !important;
    padding: 0 !important;
    opacity: 0 !important;
    transition: opacity .35s !important;
    font-size: 0 !important;
    color: transparent !important;
}
.prod-img.has-hover:hover .img-principal { opacity: 0 !important; }
.prod-img.has-hover:hover .img-hover     { opacity: 1 !important; }

/* Placeholder cuando no hay imagen o falla la carga */
.prod-ph {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    z-index: 1;
}
.prod-ph i {
    font-size: 52px;
    color: #ccc;
    transition: all .3s;
}
.prod-card:hover .prod-ph i { color: #6b7300; }
.prod-ph span {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #999;
}

/* Fallback cuando imagen rota */
.prod-ph-fallback {
    display: none;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    position: absolute;
    inset: 0;
    background: #f5f5f7;
    z-index: 2;
    align-items: center;
    justify-content: center;
}
.prod-ph-fallback i  { font-size: 48px; color: #ccc; }
.prod-ph-fallback span { font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: #999; }

/* ── Botón comparar ──────────────────────────────────────── */
.btn-comparar {
    position: absolute;
    bottom: 8px;
    left: 8px;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(0,0,0,.65);
    color: #fff;
    border: none;
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    opacity: 0;
    z-index: 4;
}
.prod-img:hover .btn-comparar { opacity: 1; }
.btn-comparar.activo,
.btn-comparar:hover { background: #CEFF04; color: #000; }

/* ── Sidebar árbol de categorías ─────────────────────────── */
.sc-principal { font-weight:700; font-size:14px; color:var(--gris1); }
.sc-sub       { font-weight:600; font-size:12px; color:var(--gris2); }
.sc-subsub    { font-weight:500; font-size:11px; color:var(--gris3); }
.sc-header    { cursor:pointer; user-select:none; display:flex; align-items:center; gap:12px; padding:10px 0 !important; transition:all .2s; }
.sc-header:hover { opacity:.8; }
.sc-arrow     { transition:transform .3s; font-size:12px; margin-left:auto; display:flex; align-items:center; }
.sc-group.open .sc-arrow { transform:rotate(90deg); }
.sc-children  { display:none; margin-left:8px; border-left:2px solid var(--borde); padding-left:12px; }
.sc-group.open > .sc-children { display:block; }
.sc-ico       { margin-right:0; flex-shrink:0; }
.sc-nombre    { flex:1; }
.count        { display:flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:50%; background:rgba(181,192,0,.14); color:#6b7300; font-weight:700; font-size:12px; flex-shrink:0; }
.sidebar-link { display:flex; align-items:center; gap:12px; padding:10px 12px !important; }
/* Ocultar precio en agotados */
.prod-card.agotado .price-main,
.prod-card.agotado .price-old,
.prod-card.agotado .price-save,
.prod-card.agotado .prod-stock { display: none !important; }
/* ── OFERTA card especial ── */
@keyframes oferta-pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(206,255,4,.6); }
    50%       { box-shadow: 0 0 0 8px rgba(206,255,4,0); }
}
.prod-card.oferta-especial {
    background: linear-gradient(135deg, #fefff0, #fffff5) !important;
    border: 1.5px solid #CEFF04 !important;
    animation: oferta-pulse 1.8s ease-in-out infinite;
    position: relative;
}
.prod-card.oferta-especial .prod-img::after {
    content: 'OFERTA';
    position: absolute;
    top: 14px;
    right: -22px;
    background: #e53935;
    color: #fff;
    font-size: 10px;
    font-weight: 800;
    padding: 3px 30px;
    transform: rotate(35deg);
    z-index: 6;
    pointer-events: none;
    letter-spacing: 1px;
}
/* ── FIX: prod-card-link siempre encima de la imagen ── */
.prod-card-link {
    position: absolute !important;
    inset: 0 !important;
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    z-index: 5 !important;
    display: block !important;
}

/* Botones interactivos por encima del link */
.btn-add-cart,
.btn-comparar,
.btn-cart {
    position: relative !important;
    z-index: 6 !important;
}
/* AÑADE al final del <style> en productos.php */

/* Card clickeable */
.prod-card {
    position: relative !important;
    cursor: pointer !important;
}
.prod-card-link {
    position: absolute !important;
    inset: 0 !important;
    z-index: 2 !important;
    display: block !important;
    width: 100% !important;
    height: 100% !important;
}
/* prod-body y botones por encima del link */
.prod-body {
    position: relative !important;
    z-index: 3 !important;
}
.btn-cart,
.btn-add-cart {
    position: relative !important;
    z-index: 3 !important;
}
</style>

<div class="container">
  <div class="breadcrumb">
    <a href="<?= SITE_URL ?>/index.php">Inicio</a><span>›</span>
    <?php if($catActual): ?>
      <a href="<?= SITE_URL ?>/productos.php">Productos</a><span>›</span><strong><?= sanitize($catActual['nombre']) ?></strong>
    <?php else: ?>
      <strong>Productos<?= $q ? " — \"$q\"" : '' ?></strong>
    <?php endif; ?>
  </div>

  <div class="products-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">
      <div class="sidebar-section">
        <h3><i class="fas fa-th-large"></i> Categorías</h3>
        <a class="sidebar-link <?= !$categoriaId?'active':'' ?>" href="<?= SITE_URL ?>/productos.php<?= $q?"?q=$q":'' ?>">
          Todos los productos <span class="count"><?= array_sum(array_column($cats,'total')) ?></span>
        </a>
        <?php
        $idsPrincipales = [1,2,3,4,5,6,7,8,9,10,11,46];
        static $catUid = 0;
        function renderSidebarCat($cat, $categoriaId, $nivel = 0) {
            global $catUid;
            $catUid++;
            $tieneHijos = !empty($cat['children']);
            $isActive   = ($cat['id'] == $categoriaId);
            $expandir   = $isActive;
            if (!$expandir && $tieneHijos) {
                foreach ($cat['children'] as $h) {
                    if ($h['id'] == $categoriaId) { $expandir = true; break; }
                    foreach (($h['children'] ?? []) as $n) {
                        if ($n['id'] == $categoriaId) { $expandir = true; break 2; }
                    }
                }
            }
            $id_elem = 'sc-' . $catUid;
            $pl      = $nivel > 0 ? ($nivel * 20) : 0;
            $clase   = $nivel === 0 ? 'sc-principal' : ($nivel === 1 ? 'sc-sub' : 'sc-subsub');
            if ($tieneHijos) {
                $open = $expandir ? ' open' : '';
                echo '<div class="sc-group'.$open.'">';
                echo '<div class="sc-header '.$clase.'" onclick="toggleSC(\''.$id_elem.'\')" style="padding-left:'.$pl.'px">';
                echo '<i class="fas '.sanitize($cat['icono']).' sc-ico"></i>';
                echo '<span class="sc-nombre">'.sanitize($cat['nombre']).'</span>';
                echo '<span class="count">'.$cat['total'].'</span>';
                echo '<i class="fas fa-chevron-right sc-arrow"></i>';
                echo '</div>';
                echo '<div class="sc-children" id="'.$id_elem.'">';
                foreach ($cat['children'] as $hijo) { renderSidebarCat($hijo, $categoriaId, $nivel + 1); }
                echo '</div></div>';
            } else {
                echo '<a class="sidebar-link sc-leaf '.$clase.($isActive?' active':'').'" href="'.SITE_URL.'/productos.php?categoria='.$cat['id'].'" style="padding-left:'.(10+$pl).'px">';
                echo '<i class="fas '.sanitize($cat['icono']).' sc-ico"></i>';
                echo '<span class="sc-nombre">'.sanitize($cat['nombre']).'</span>';
                echo '<span class="count">'.$cat['total'].'</span>';
                echo '</a>';
            }
        }
        foreach ($idsPrincipales as $pid) {
            if (isset($catById2[$pid])) renderSidebarCat($catById2[$pid], $categoriaId);
        }
        ?>
      </div>

      <div class="sidebar-section">
        <h3><i class="fas fa-tag"></i> Precio (S/)</h3>
        <form method="GET">
          <?php if($categoriaId): ?><input type="hidden" name="categoria" value="<?= $categoriaId ?>"><?php endif; ?>
          <?php if($q): ?><input type="hidden" name="q" value="<?= $q ?>"><?php endif; ?>
          <?php if($marcaFilt): ?><input type="hidden" name="marca" value="<?= sanitize($marcaFilt) ?>"><?php endif; ?>
          <?php if($vaFilt): ?><input type="hidden" name="va" value="<?= $vaFilt ?>"><?php endif; ?>
          <div class="price-range">
            <input type="number" name="pmin" placeholder="Mín" value="<?= $precioMin?:'' ?>" min="0">
            <span style="color:var(--gris3);">–</span>
            <input type="number" name="pmax" placeholder="Máx" value="<?= $precioMax<99999?$precioMax:'' ?>" min="0">
          </div>
          <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrar</button>
        </form>
      </div>

      <?php if(!empty($marcas)): ?>
      <div class="sidebar-section">
        <h3><i class="fas fa-building"></i> Marcas</h3>
        <a class="sidebar-link <?= !$marcaFilt?'active':'' ?>" href="<?= SITE_URL ?>/productos.php<?= $categoriaId?"?categoria=$categoriaId":'' ?>">Todas las marcas</a>
        <?php foreach($marcasRes as $marca): ?>
          <a class="sidebar-link <?= $marcaFilt===$marca['nombre']?'active':'' ?>" href="<?= SITE_URL ?>/productos.php?marca=<?= urlencode($marca['nombre']) ?><?= $categoriaId?"&categoria=$categoriaId":'' ?>">
            <span><?= sanitize($marca['nombre']) ?></span>
            <span class="count"><?= $marca['total'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if(!empty($vasRaw)): ?>
      <div class="sidebar-section">
        <h3><i class="fas fa-bolt"></i> Potencia (VA)</h3>
        <a class="sidebar-link <?= !$vaFilt?'active':'' ?>" href="<?= SITE_URL ?>/productos.php<?= $categoriaId?"?categoria=$categoriaId":'' ?>">Todas las potencias</a>
        <?php foreach($vasRaw as $va): ?>
          <a class="sidebar-link <?= $vaFilt==(int)$va?'active':'' ?>" href="<?= SITE_URL ?>/productos.php?va=<?= (int)$va ?><?= $categoriaId?"&categoria=$categoriaId":'' ?>">
            <i class="fas fa-bolt" style="font-size:10px;"></i> <?= (int)$va ?> VA
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="sidebar-section">
        <h3><i class="fas fa-star"></i> Filtros rápidos</h3>
        <a class="sidebar-link" href="<?= SITE_URL ?>/productos.php?orden=oferta<?= $categoriaId?"&categoria=$categoriaId":'' ?>"><i class="fas fa-tags"></i> En oferta</a>
        <a class="sidebar-link" href="<?= SITE_URL ?>/productos.php?orden=mas_vendidos<?= $categoriaId?"&categoria=$categoriaId":'' ?>"><i class="fas fa-fire"></i> Más vendidos</a>
        <a class="sidebar-link" href="<?= SITE_URL ?>/productos.php?orden=relevancia<?= $categoriaId?"&categoria=$categoriaId":'' ?>"><i class="fas fa-star"></i> Más relevantes</a>
        <a class="sidebar-link" href="<?= SITE_URL ?>/productos.php?orden=precio_asc<?= $categoriaId?"&categoria=$categoriaId":'' ?>"><i class="fas fa-sort-amount-up"></i> Menor precio</a>
        <a class="sidebar-link" href="<?= SITE_URL ?>/productos.php?orden=precio_desc<?= $categoriaId?"&categoria=$categoriaId":'' ?>"><i class="fas fa-sort-amount-down"></i> Mayor precio</a>
      </div>

      <div class="sidebar-section">
        <h3><i class="fas fa-th-large"></i> Modo de carga</h3>
        <a class="sidebar-link <?= !$scrollInf?'active':'' ?>" href="<?= SITE_URL ?>/productos.php?<?= http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'orden'=>$orden,'scroll'=>'0'])) ?>"><i class="fas fa-list-ol"></i> Paginación clásica</a>
        <a class="sidebar-link <?= $scrollInf?'active':'' ?>"  href="<?= SITE_URL ?>/productos.php?<?= http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'orden'=>$orden,'scroll'=>'1'])) ?>"><i class="fas fa-infinity"></i> Scroll infinito</a>
      </div>
    </aside>

    <!-- CONTENIDO -->
    <div>
      <?php if($catActual): ?>
        <div style="background:#fff;border-radius:var(--rl);padding:24px;margin-bottom:20px;border:1.5px solid var(--borde);display:flex;align-items:center;gap:16px;box-shadow:var(--sombra);">
          <div style="width:56px;height:56px;background:rgba(181,192,0,.14);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:26px;color:#6b7300;flex-shrink:0;">
            <i class="fas <?= sanitize($catActual['icono']) ?>"></i>
          </div>
          <div>
            <h1 style="font-size:22px;font-weight:900;color:#0d0d0d;margin-bottom:4px;"><?= sanitize($catActual['nombre']) ?></h1>
            <p style="color:var(--gris3);font-size:13px;"><?= sanitize($catActual['descripcion']??'') ?></p>
          </div>
        </div>
      <?php elseif($q): ?>
        <div style="background:#fff;border-radius:var(--rl);padding:18px 22px;margin-bottom:20px;border:1.5px solid var(--borde);box-shadow:var(--sombra);">
          <h1 style="font-size:20px;font-weight:800;color:#0d0d0d;">Resultados para: <span style="color:#6b7300;">"<?= sanitize($q) ?>"</span></h1>
        </div>
      <?php endif; ?>

      <div class="products-toolbar">
        <span class="products-count">
          <strong><?= $total ?></strong> productos encontrados
          <?php if($vaFilt): ?><span style="margin-left:8px;background:#CEFF04;color:#000;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;"><?= $vaFilt ?> VA</span><?php endif; ?>
          <?php if($scrollInf): ?><span style="margin-left:8px;background:rgba(181,192,0,.14);color:#6b7300;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:700;"><i class="fas fa-infinity"></i> Scroll infinito</span><?php endif; ?>
        </span>
        <div style="display:flex;gap:10px;align-items:center;">
          <label style="font-size:13px;color:var(--gris3);">Ordenar:</label>
          <select class="sort-select" onchange="location.href=this.value">
            <?php
            $base = SITE_URL.'/productos.php?'.http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'scroll'=>$scrollInf?'1':'0']));
            $opts = ['recientes'=>'Más recientes','relevancia'=>'Más relevantes','mas_vendidos'=>'🔥 Más vendidos','nombre'=>'Nombre A-Z','precio_asc'=>'Precio ↑','precio_desc'=>'Precio ↓','oferta'=>'En oferta primero'];
            foreach($opts as $v=>$l): ?>
              <option value="<?= $base ?>&orden=<?= $v ?>" <?= $orden===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <?php if(empty($productos)): ?>
        <div class="no-results" style="background:#fff;border-radius:var(--rl);border:1.5px dashed var(--borde);">
          <i class="fas fa-search"></i>
          <p style="font-size:18px;font-weight:700;color:#0d0d0d;margin-bottom:8px;">No encontramos productos</p>
          <p>Prueba con otros filtros o explora nuestras categorías</p>
          <a href="<?= SITE_URL ?>/productos.php" class="btn-main" style="margin-top:20px;display:inline-flex;"><i class="fas fa-th"></i> Ver todos</a>
        </div>
      <?php else: ?>
        <div class="prod-grid" id="prod-grid">
        <?php foreach($productos as $p):
    $precio = $p['precio_oferta'] ?? $p['precio'];
    $desc   = $p['descuento'];
    $msv    = ($p['total_vendido']??0) >= 5;
    $img2   = !empty($p['imagen2']) ? $p['imagen2'] : '';
?>
<div class="prod-card<?= $p['stock'] <= 0 ? ' agotado' : '' ?><?= $p['categoria_id'] == 46 ? ' oferta-especial' : '' ?>" data-id="<?= $p['id'] ?>">
    <a class="prod-card-link" href="<?= SITE_URL ?>/producto.php?id=<?= $p['id'] ?>"></a>
    <div class="prod-img<?= $img2 ? ' has-hover' : '' ?>">
        <?php if($p['stock'] <= 0): ?>
            <span class="badge-agotado">✕ Agotado</span>
        <?php endif; ?>

        <?php if($desc): ?>
            <span class="badge-desc"><?= $desc ?>% OFF</span>
        <?php endif; ?>

        <?php if($msv): ?>
            <span class="badge-hot" style="background:#e53935;color:#fff;">&#128293; MÁS VENDIDO</span>
        <?php elseif($p['destacado']): ?>
            <span class="badge-hot">&#9733; DEST.</span>
        <?php endif; ?>

        <?php if (!empty($p['canje_puntos']) && (int)$p['canje_puntos'] > 0): ?>
            <span class="badge-canje">Canjea <?= number_format((int)$p['canje_puntos']) ?> pts</span>
        <?php endif; ?>

        <?php if(!empty($p['imagen'])): ?>
            <img class="img-principal"
                 src="<?= SITE_URL ?>/<?= htmlspecialchars($p['imagen']) ?>"
                 alt="<?= htmlspecialchars($p['nombre']) ?>"
                 loading="lazy"
                 onerror="this.style.display='none';var f=this.parentElement.querySelector('.prod-ph-fallback');if(f)f.style.display='flex';">

            <?php if($img2): ?>
                <img class="img-hover"
                     src="<?= SITE_URL ?>/<?= $img2 ?>"
                     alt=""
                     loading="lazy"
                     onerror="this.style.display='none';">
            <?php endif; ?>

            <!-- Fallback si la imagen falla al cargar -->
            <div class="prod-ph-fallback">
                <i class="fas <?= htmlspecialchars($p['icono']) ?>"></i>
                <span><?= htmlspecialchars($p['marca']) ?></span>
            </div>

        <?php else: ?>
            <div class="prod-ph">
                <i class="fas <?= htmlspecialchars($p['icono']) ?>"></i>
                <span><?= htmlspecialchars($p['marca']) ?></span>
            </div>
        <?php endif; ?>

        <?php if (isLoggedIn()): ?>
        <button class="btn-comparar"
                data-id="<?= $p['id'] ?>"
                data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                data-precio="<?= formatPrice($precio) ?>"
                data-img="<?= !empty($p['imagen']) ? SITE_URL.'/'.$p['imagen'] : '' ?>">
            <i class="fas fa-exchange-alt"></i>
        </button>
        <?php endif; ?>
    </div>

    <div class="prod-body">
        <div class="prod-brand"><?= htmlspecialchars($p['marca']) ?></div>
        <div class="prod-name">
            <a href="<?= SITE_URL ?>/producto.php?id=<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></a>
        </div>

        <?php if(($p['potencia_va']??0) > 0): ?>
            <div style="font-size:11px;color:#6b7300;font-weight:700;margin-bottom:2px;">
                <i class="fas fa-bolt"></i> <?= (int)$p['potencia_va'] ?> VA
            </div>
        <?php endif; ?>

        <?= renderPrecioCarrito($p, $precio, $desc, $p['id']) ?>
    </div>
</div>
<?php endforeach; ?>
        </div>

        <?php if($scrollInf): ?>
          <div id="sentinel" style="height:20px;margin:40px 0;"></div>
        <?php else: ?>
          <?php if($totalPaginas > 1): ?>
            <div style="display:flex;gap:10px;margin:40px 0;justify-content:center;align-items:center;">
              <?php if($pagina > 1): ?>
                <a href="<?= SITE_URL ?>/productos.php?<?= http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'pagina'=>$pagina-1,'orden'=>$orden,'scroll'=>$scrollInf?'1':'0'])) ?>" class="btn-main"><i class="fas fa-chevron-left"></i> Anterior</a>
              <?php endif; ?>
              <span style="color:var(--gris3);">Página <strong><?= $pagina ?></strong> de <strong><?= $totalPaginas ?></strong></span>
              <?php if($pagina < $totalPaginas): ?>
                <a href="<?= SITE_URL ?>/productos.php?<?= http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'pagina'=>$pagina+1,'orden'=>$orden,'scroll'=>$scrollInf?'1':'0'])) ?>" class="btn-main">Siguiente <i class="fas fa-chevron-right"></i></a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if($scrollInf): ?>
<script>
new IntersectionObserver(e => {
    if(e[0].isIntersecting) cargarMas();
}, {rootMargin:'200px'}).observe(document.getElementById('sentinel'));

let pagActual = 2;
function cargarMas() {
    fetch('<?= SITE_URL ?>/productos.php?<?= http_build_query(array_filter(['categoria'=>$categoriaId,'q'=>$q,'marca'=>$marcaFilt,'va'=>$vaFilt,'ajax'=>'1','orden'=>$orden,'scroll'=>'1'])) ?>&pagina=' + pagActual)
        .then(r => r.json())
        .then(d => {
            if(d.html) document.getElementById('prod-grid').insertAdjacentHTML('beforeend', d.html);
            pagActual++;
            if(!d.hayMas) document.getElementById('sentinel').style.display = 'none';
        });
}
</script>
<?php endif; ?>

<script>
function toggleSC(elemId) {
    const elem = document.getElementById(elemId);
    if (elem && elem.parentElement) {
        elem.parentElement.classList.toggle('open');
    }
}

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