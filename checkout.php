<?php
require_once 'includes/config.php';
requireLogin();
$pdo = getDB();

// Costos delivery por distrito (sede: Santa Anita)
$distritosLima = [
  'Santa Anita'=>8,'Ate'=>8,'El Agustino'=>8,'San Luis'=>8,
  'La Victoria'=>12,'Ate Vitarte'=>12,'San Juan de Lurigancho'=>12,'Cieneguilla'=>12,'Chaclacayo'=>12,
  'Lima Cercado'=>15,'Breña'=>15,'La Molina'=>15,'Lince'=>15,'Rímac'=>15,'San Borja'=>15,
  'Surquillo'=>15,'Miraflores'=>15,'San Isidro'=>15,'San Miguel'=>15,'Jesús María'=>15,
  'Magdalena del Mar'=>15,'Pueblo Libre'=>15,'Barranco'=>15,'Chorrillos'=>15,
  'Santiago de Surco'=>15,'Villa María del Triunfo'=>15,
  'Los Olivos'=>20,'San Martín de Porres'=>20,'Independencia'=>20,'Comas'=>20,
  'Carabayllo'=>20,'Puente Piedra'=>20,'Callao'=>20,'Bellavista'=>20,
  'La Perla'=>20,'Carmen de la Legua'=>20,'Ventanilla'=>20,'Mi Perú'=>20,
  'Lurigancho'=>20,'Lurín'=>20,'Pachacamac'=>20,'Villa El Salvador'=>20,
  'San Juan de Miraflores'=>20,
  'Ancón'=>25,'Santa Rosa'=>25,'Punta Hermosa'=>25,'Punta Negra'=>25,
  'San Bartolo'=>25,'Santa María del Mar'=>25,'Pucusana'=>25,
];
ksort($distritosLima);
$costoProvincias = 30.00;

$s = $pdo->prepare("SELECT c.id, c.usuario_id, c.session_id, c.producto_id, c.cantidad, c.es_canje_puntos, c.creado_en,
    p.nombre, p.precio, p.precio_oferta, p.stock, p.marca, p.imagen, p.canje_puntos as producto_canje_puntos, cat.icono
    FROM carrito c JOIN productos p ON c.producto_id=p.id
    JOIN categorias cat ON p.categoria_id=cat.id
    WHERE c.usuario_id=? AND p.activo=1 ORDER BY c.creado_en DESC");
$s->execute([$_SESSION['usuario_id']]);
$items = $s->fetchAll();
if (empty($items)) { header('Location: '.SITE_URL.'/carrito.php'); exit; }

// Asegurar que exista la tabla control_puntos (para auditoría de movimientos)
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS control_puntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    producto_id INT NULL,
    puntos INT NOT NULL,
    tipo_movimiento VARCHAR(32) NOT NULL,
    descripcion TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
  // No hacemos nada; si falla, la inserción posterior lanzará el error y se registrará en el log
}

$subtotal = 0;
foreach ($items as $it) {
    if (!empty($it['es_canje_puntos'])) {
        continue;
    }
  $precioItem = ($it['precio_oferta'] ?? $it['precio']);
  $precioItem = precioFinal($precioItem);
  $subtotal += $precioItem * $it['cantidad'];
}

// Validación frontal de stock para evitar que el checkout llegue a una transacción
// y luego falle en UPDATE de productos sin mensaje útil al usuario.
foreach ($items as $it) {
  if (!empty($it['es_canje_puntos'])) {
    continue;
  }
  if ((int)$it['cantidad'] > (int)($it['stock'] ?? 0)) {
    $errores[] = 'No hay stock suficiente para ' . sanitize($it['nombre']) . '.';
  }
}

function validarCupon($pdo, $codigo, $subtotal, $usuarioId = null) {
  $codigo = trim($codigo);
  if ($codigo === '') {
    return [null, 0.0, '', null];
  }
  try {
    $cupon = null;
    $usuarioCuponId = null;
    
    // 1. Buscar en cupones personales asignados (BIENVENIDA-XXXXX, CUMPLE-XXXXX, etc)
    if ($usuarioId) {
      $stmtPersonal = $pdo->prepare("
        SELECT uc.id as usuario_cupon_id, uc.fecha_expiracion, uc.usado, 
               c.* FROM usuario_cupones uc
        JOIN cupones c ON uc.cupon_id = c.id
        WHERE uc.codigo_personal = ? AND uc.usuario_id = ? AND uc.usado = 0
        LIMIT 1
      ");
      $stmtPersonal->execute([$codigo, $usuarioId]);
      $cuponPersonal = $stmtPersonal->fetch();
      
      if ($cuponPersonal) {
        $cupon = $cuponPersonal;
        $usuarioCuponId = $cuponPersonal['usuario_cupon_id'];
        
        // Validar fecha de expiración personal
        $now = new DateTimeImmutable('now');
        if (!empty($cupon['fecha_expiracion'])) {
          $fin = new DateTimeImmutable($cupon['fecha_expiracion'].' 23:59:59');
          if ($now > $fin) {
            return [null, 0.0, 'El cupón ya expiró.', null];
          }
        }
      }
    }
    
    // 2. Si no encontró cupón personal, buscar cupón público
    if (!$cupon) {
      $stmt = $pdo->prepare("SELECT * FROM cupones WHERE codigo = ? AND activo = 1 LIMIT 1");
      $stmt->execute([$codigo]);
      $cupon = $stmt->fetch();
      if (!$cupon) {
        return [null, 0.0, 'Código de cupón no válido o inactivo.', null];
      }
    }
    // Si es un cupón público con límite de usos, verificar que queden usos disponibles
    if (!$usuarioCuponId && $cupon && isset($cupon['limite_usos']) && $cupon['limite_usos'] !== null) {
      if ((int)$cupon['limite_usos'] <= 0) {
        return [null, 0.0, 'Este cupón ya no tiene usos disponibles.', null];
      }
    }
    
    // 3. Validar fechas y condiciones del cupón
    $now = new DateTimeImmutable('now');
    if (!empty($cupon['fecha_inicio']) && !$usuarioCuponId) {
      $inicio = new DateTimeImmutable($cupon['fecha_inicio']);
      if ($now < $inicio) {
        return [null, 0.0, 'El cupón aún no está vigente.', null];
      }
    }
    if (!empty($cupon['fecha_vencimiento']) && !$usuarioCuponId) {
      $fin = new DateTimeImmutable($cupon['fecha_vencimiento'].' 23:59:59');
      if ($now > $fin) {
        return [null, 0.0, 'El cupón ya expiró.', null];
      }
    }
    if (!empty($cupon['compra_minima']) && $subtotal < (float)$cupon['compra_minima']) {
      return [null, 0.0, 'Este cupón requiere una compra mínima de S/ '.number_format((float)$cupon['compra_minima'],2).'.', null];
    }
    
    // 4. Calcular descuento
    $descuento = 0.0;
    if (($cupon['tipo_descuento'] ?? 'porcentaje') === 'monto') {
      $descuento = min((float)$cupon['descuento'], $subtotal);
    } else {
      $percent = min(max((float)$cupon['descuento'], 0), 100);
      $descuento = round($subtotal * $percent / 100, 2);
    }
    return [$cupon, $descuento, '', $usuarioCuponId];
  } catch (Exception $e) {
    return [null, 0.0, 'No se pudo validar el cupón. Intenta nuevamente.', null];
  }
}

$codigoCupon = sanitize($_POST['cupon_codigo'] ?? '');
$cuponAplicado = null;
$descuentoCupon = 0.0;
$usuarioCuponIdAplicado = null;
$errores = [];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $tipoEnvio     = in_array($_POST['tipo_envio']??'',['delivery','provincia','recojo_tienda'])?$_POST['tipo_envio']:'';
  $metodoPago    = in_array($_POST['metodo_pago']??'',['yape','plin','transferencia','tarjeta'])?$_POST['metodo_pago']:'';
  $direccion     = sanitize($_POST['direccion']??'');
  $distrito      = sanitize($_POST['distrito']??'');
  $provDest      = sanitize($_POST['provincia_destino']??'');
  $referencia    = sanitize($_POST['referencia']??'');
  $notas         = sanitize($_POST['notas']??'');
  $aplicarCupon  = isset($_POST['aplicar_cupon']);

  // Normalizar: si es delivery y no hay dirección pero sí distrito, usar distrito+referencia como fallback
  if ($tipoEnvio === 'delivery' && empty($direccion) && !empty($distrito)) {
    $fallback = $distrito;
    if (!empty($referencia)) $fallback .= ' - ' . $referencia;
    $direccion = $fallback;
  }

  if ($codigoCupon !== '') {
      [$cuponAplicado, $descuentoCupon, $errorCupon, $usuarioCuponIdAplicado] = validarCupon($pdo, $codigoCupon, $subtotal, $_SESSION['usuario_id']);
      if ($errorCupon) {
          $errores[] = $errorCupon;
      }
  }

  if (!$aplicarCupon) {
    if (!$tipoEnvio)  $errores[]='Selecciona el tipo de entrega.';
    if (!$metodoPago) $errores[]='Selecciona el método de pago.';
    if ($tipoEnvio==='delivery' && !$distrito)  $errores[]='Selecciona tu distrito.';
    if (in_array($tipoEnvio, ['delivery','provincia'], true) && !$direccion) {
      $errores[]='Ingresa tu dirección.';
      // Debug: guardar POST para investigar por qué llega vacía la dirección
      try {
        $logDir = __DIR__ . '/tmp_logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        $fn = $logDir . '/checkout_post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.log';
        file_put_contents($fn, "POST DATA:\n" . print_r($_POST, true));
      } catch (Throwable $e) {
        error_log('checkout debug write failed: ' . $e->getMessage());
      }
    }
    if ($tipoEnvio==='provincia' && !$provDest) $errores[]='Indica tu ciudad/provincia.';
  }

  if (empty($errores) && !$aplicarCupon) {
    $envio = match($tipoEnvio) {
      'delivery'      => $distritosLima[$distrito] ?? COSTO_DELIVERY,
      'provincia'     => $costoProvincias,
      default         => 0
    };
    $total  = $subtotal + $envio - $descuentoCupon;
    if ($total < 0) {
      $total = 0;
    }
    $codigo = generateOrderCode();
    $dirFinal = ($tipoEnvio==='provincia') ? $provDest : $direccion;
    try {
      $pdo->beginTransaction();

      // Detectar columnas disponibles en `pedidos` y preparar INSERT dinámico
      // Asegurar columnas para guardar información de cupón/descuento en pedidos
      try {
        $colCheck = $pdo->prepare("SHOW COLUMNS FROM pedidos LIKE 'cupon_codigo'");
        $colCheck->execute();
        if ($colCheck->rowCount() === 0) {
          $pdo->exec("ALTER TABLE pedidos ADD COLUMN cupon_codigo VARCHAR(64) NULL");
        }
        $colCheck = $pdo->prepare("SHOW COLUMNS FROM pedidos LIKE 'descuento'");
        $colCheck->execute();
        if ($colCheck->rowCount() === 0) {
          $pdo->exec("ALTER TABLE pedidos ADD COLUMN descuento DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
      } catch (Exception $e) {
        // Si falla el alter (permisos/DB), continuamos sin insertar esas columnas
      }

      $colsInfo = $pdo->query("SHOW COLUMNS FROM pedidos")->fetchAll(PDO::FETCH_ASSOC);
      $hasEstado = false; $hasDireccion = false; $hasDistrito = false; $hasReferencia = false; $hasNotas = false;
      foreach ($colsInfo as $col) {
        $f = $col['Field'] ?? '';
        if ($f === 'estado') $hasEstado = true;
        if ($f === 'direccion_entrega') $hasDireccion = true;
        if ($f === 'distrito_entrega') $hasDistrito = true;
        if ($f === 'referencia') $hasReferencia = true;
        if ($f === 'notas') $hasNotas = true;
      }

      $columns = [];
      $placeholders = [];
      $params = [];

      // columnas base
      $columns[] = 'usuario_id'; $placeholders[] = '?'; $params[] = $_SESSION['usuario_id'];
      $columns[] = 'total'; $placeholders[] = '?'; $params[] = $total;
      if ($hasEstado) { $columns[] = 'estado'; $placeholders[] = '?'; $params[] = 'pendiente'; }
      $columns[] = 'metodo_pago'; $placeholders[] = '?'; $params[] = $metodoPago;
      $columns[] = 'tipo_entrega'; $placeholders[] = '?'; $params[] = $tipoEnvio;

      if ($hasDireccion) { $columns[] = 'direccion_entrega'; $placeholders[] = '?'; $params[] = $direccion; }
      if ($hasDistrito)  { $columns[] = 'distrito_entrega';  $placeholders[] = '?'; $params[] = ($tipoEnvio==='provincia' ? $provDest : $distrito); }
      if ($hasReferencia){ $columns[] = 'referencia'; $placeholders[] = '?'; $params[] = $referencia; }

      if ($hasNotas)     { $columns[] = 'notas'; $placeholders[] = '?'; $params[] = $notas; }

      // Si la tabla pedidos tiene columnas para cupon/descuento, incluirlas
      $colNames = array_column($colsInfo, 'Field');
      if (in_array('cupon_codigo', $colNames, true)) {
        $columns[] = 'cupon_codigo'; $placeholders[] = '?'; $params[] = ($codigoCupon !== '' ? $codigoCupon : null);
      }
      if (in_array('descuento', $colNames, true)) {
        $columns[] = 'descuento'; $placeholders[] = '?'; $params[] = (float)($descuentoCupon ?? 0.0);
      }

      $columns[] = 'creado_en'; $placeholders[] = 'NOW()';

      $sql = sprintf("INSERT INTO pedidos (%s) VALUES (%s)", implode(', ', $columns), implode(', ', $placeholders));
      $stmtPedido = $pdo->prepare($sql);
      $stmtPedido->execute($params);

      $pedidoId = $pdo->lastInsertId();

      // Comprobar si `detalle_pedidos` tiene columna `estado`
      $colsDetalle = $pdo->query("SHOW COLUMNS FROM detalle_pedidos")->fetchAll(PDO::FETCH_ASSOC);
      $detalleHasEstado = false;
      foreach ($colsDetalle as $col) { if (($col['Field'] ?? '') === 'estado') { $detalleHasEstado = true; break; } }

      foreach ($items as $it) {
        if ($it['es_canje_puntos']) {
          $pr = 0;
          $subtotalItem = 0;
        } else {
          $pr = $it['precio_oferta'] ?? $it['precio'];
          $pr = precioFinal($pr);
          $subtotalItem = $pr * $it['cantidad'];
        }
        if ($detalleHasEstado) {
          $stmtDetalle = $pdo->prepare("INSERT INTO detalle_pedidos(pedido_id,producto_id,cantidad,precio_unitario,subtotal,estado)VALUES(?,?,?,?,?,?)");
          $stmtDetalle->execute([$pedidoId,$it['producto_id'],$it['cantidad'],$pr,$subtotalItem,'pendiente']);
        } else {
          $stmtDetalle = $pdo->prepare("INSERT INTO detalle_pedidos(pedido_id,producto_id,cantidad,precio_unitario,subtotal)VALUES(?,?,?,?,?)");
          $stmtDetalle->execute([$pedidoId,$it['producto_id'],$it['cantidad'],$pr,$subtotalItem]);
        }

        $stmtStock = $pdo->prepare("UPDATE productos SET stock=stock-? WHERE id=? AND stock>=?");
        $stmtStock->execute([$it['cantidad'],$it['producto_id'],$it['cantidad']]);
        if ($stmtStock->rowCount() !== 1) {
          throw new Exception('Stock insuficiente para ' . sanitize($it['nombre']) . '.');
        }
      }
      // Asignar puntos por compra: 1 punto por cada S/10 del total (solo si la transacción sigue sin errores)
      $puntosGanados = (int)floor($total / 10);
      if ($puntosGanados > 0) {
        $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$puntosGanados, $_SESSION['usuario_id']]);
        $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, NULL, ?, 'GANANCIA', ?, NOW())")
          ->execute([$_SESSION['usuario_id'], $puntosGanados, 'Puntos por compra #'.$pedidoId]);
      }
      
      $pdo->prepare("DELETE FROM carrito WHERE usuario_id=?")->execute([$_SESSION['usuario_id']]);
      
      // Marcar cupón personal como usado si fue aplicado
      if ($usuarioCuponIdAplicado) {
        $pdo->prepare("UPDATE usuario_cupones SET usado = 1 WHERE id = ?")->execute([$usuarioCuponIdAplicado]);
      }
      // Si se aplicó un cupón público (no personal) con límite de usos, decrementar contador
      if (!$usuarioCuponIdAplicado && !empty($cuponAplicado) && isset($cuponAplicado['id']) && isset($cuponAplicado['limite_usos']) && $cuponAplicado['limite_usos'] !== null) {
        $upd = $pdo->prepare("UPDATE cupones SET limite_usos = limite_usos - 1 WHERE id = ? AND (limite_usos IS NULL OR limite_usos > 0)");
        $upd->execute([$cuponAplicado['id']]);
        if ($upd->rowCount() !== 1) {
          throw new Exception('El cupón se agotó mientras procesábamos tu pedido. Intenta con otro cupón.');
        }
      }
      
      $pdo->commit();
      header('Location: '.SITE_URL.'/pedido-exitoso.php?id='.$pedidoId); exit;
    } catch(Exception $e) {
      // ✓ Verificar si hay transacción activa antes de rollBack
      if ($pdo->inTransaction()) {
        $pdo->rollBack();
      }
      error_log('checkout: ' . $e->getMessage());
      $errores[] = 'Error al procesar. Intenta nuevamente.';
      if (defined('APP_DEBUG') && APP_DEBUG) {
        $errores[] = 'Detalle: ' . sanitize($e->getMessage());
      }
    }
  }
}

$user=$pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $user->execute([$_SESSION['usuario_id']]); $usuario=$user->fetch();
$pageTitle='Finalizar Compra'; include 'includes/header.php';
?>
<div class="container">
  <div class="breadcrumb"><a href="<?=SITE_URL?>/index.php">Inicio</a><span>›</span><a href="<?=SITE_URL?>/carrito.php">Carrito</a><span>›</span><strong>Checkout</strong></div>
  <h1 class="page-title"><i class="fas fa-lock"></i> Finalizar Compra</h1>

  <?php if (!empty($errores)): ?>
    <div class="alert alert-error" style="margin-bottom:20px;">
      <i class="fas fa-times-circle"></i>
      <ul style="margin:0;padding-left:16px;"><?php foreach($errores as $e): ?><li><?=$e?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="POST" id="checkout-form">
  <div class="checkout-layout">
    <div>

      <!-- TIPO ENTREGA -->
      <div class="checkout-block">
        <div class="checkout-section-title"><i class="fas fa-truck"></i> Tipo de Entrega</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">

          <label class="option-card <?=($_POST['tipo_envio']??'delivery')==='delivery'?'selected':''?>">
            <input type="radio" name="tipo_envio" value="delivery" <?=($_POST['tipo_envio']??'delivery')==='delivery'?'checked':''?> onchange="switchEnvio('delivery')">
            <div class="option-card-inner">
              <div class="option-icon"><i class="fas fa-motorcycle"></i></div>
              <div><div class="option-title">Delivery Lima</div><div class="option-subtitle">Varía por distrito</div><div class="option-price" style="color:#D7E022;">Desde S/ 8</div></div>
            </div>
          </label>

          <label class="option-card <?=($_POST['tipo_envio']??'')==='provincia'?'selected':''?>">
            <input type="radio" name="tipo_envio" value="provincia" <?=($_POST['tipo_envio']??'')==='provincia'?'checked':''?> onchange="switchEnvio('provincia')">
            <div class="option-card-inner">
              <div class="option-icon"><i class="fas fa-map-marked-alt"></i></div>
              <div><div class="option-title">Provincia</div><div class="option-subtitle">Envío nacional</div><div class="option-price" style="color:#D7E022;">S/ <?=number_format($costoProvincias,2)?></div></div>
            </div>
          </label>

          <label class="option-card <?=($_POST['tipo_envio']??'')==='recojo_tienda'?'selected':''?>">
            <input type="radio" name="tipo_envio" value="recojo_tienda" <?=($_POST['tipo_envio']??'')==='recojo_tienda'?'checked':''?> onchange="switchEnvio('recojo_tienda')">
            <div class="option-card-inner">
              <div class="option-icon"><i class="fas fa-store"></i></div>
              <div><div class="option-title">Recojo tienda</div><div class="option-subtitle">Jr. Paruro 1322</div><div class="option-price" style="color:#4caf50;">GRATIS</div></div>
            </div>
          </label>
        </div>

        <!-- Delivery Lima campos -->
        <div id="fields-delivery" style="margin-top:16px;<?=($_POST['tipo_envio']??'delivery')!=='delivery'?'display:none':''?>">
          <div class="form-group" style="margin-bottom:12px;">
            <label>Distrito * <small style="color:var(--gris3);font-weight:400;">(costo varía según distancia desde Santa Anita)</small></label>
            <select name="distrito" id="select-distrito" onchange="updateCostoDistrito(this.value)"
                style="width:100%;padding:11px 14px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);color:var(--blanco);font-size:14px;">
              <option value="">— Elige tu distrito —</option>
              <?php foreach($distritosLima as $d=>$c): ?>
                <option value="<?=htmlspecialchars($d)?>" data-costo="<?=$c?>" <?=($_POST['distrito']??'')===$d?'selected':''?>>
                  <?=htmlspecialchars($d)?> — S/ <?=number_format($c,2)?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="costo-info" style="margin-top:6px;font-size:12px;color:#D7E022;display:none;">
              <i class="fas fa-info-circle"></i> Envío a este distrito: <strong id="costo-txt"></strong>
            </div>
          </div>
          <div class="form-group">
            <label>Dirección completa *</label>
            <input type="text" name="direccion" value="<?=sanitize($_POST['direccion']??$usuario['direccion_entrega']??'')?>" placeholder="Av. / Jr. / Calle, número, piso...">
          </div>
          <div class="form-group">
            <label>Referencia</label>
            <input type="text" name="referencia" value="<?=sanitize($_POST['referencia']??'')?>" placeholder="Cerca de..., color de puerta, etc.">
          </div>
        </div>

        <!-- Provincia campos -->
        <div id="fields-provincia" style="margin-top:16px;<?=($_POST['tipo_envio']??'')==='provincia'?'':'display:none'?>">
          <div class="form-group">
            <label>Ciudad / Provincia *</label>
            <input type="text" name="provincia_destino" value="<?=sanitize($_POST['provincia_destino']??'')?>" placeholder="Ej: Arequipa, Trujillo, Cusco...">
          </div>
          <div class="form-group">
            <label>Dirección *</label>
            <input type="text" name="direccion" value="<?=sanitize($_POST['direccion']??'')?>" placeholder="Av. / Jr. / Calle, número...">
          </div>
          <div style="background:rgba(215,224,34,.08);border:1px solid rgba(215,224,34,.25);border-radius:10px;padding:12px;font-size:13px;color:var(--gris2);">
            <i class="fas fa-info-circle" style="color:#D7E022;"></i>
            Costo envío a provincia: <strong style="color:#D7E022;">S/ <?=number_format($costoProvincias,2)?></strong> · Tiempo estimado: <strong>3–5 días hábiles</strong>
          </div>
        </div>

        <!-- Recojo tienda info -->
        <div id="fields-recojo" style="margin-top:16px;<?=($_POST['tipo_envio']??'')==='recojo_tienda'?'':'display:none'?>">
          <div style="background:rgba(76,175,80,.08);border:1px solid rgba(76,175,80,.25);border-radius:10px;padding:14px;font-size:13px;color:var(--gris2);">
            <i class="fas fa-store" style="color:#4caf50;"></i> <strong style="color:#4caf50;">Recojo GRATIS</strong> — Nuestras sedes:<br><br>
            <b>• Lima:</b> Jr. Paruro Nº 1322 - Sótano Tda S112<br>
            <b>• La Molina:</b> Av. Melgarejo Nº 595<br>
            <b>• Ate:</b> C.C. Plaza Vitarte, Block F Tda. 304<br><br>
            <i class="fas fa-clock"></i> Lun–Sáb 9:00am – 8:00pm
          </div>
        </div>
      </div>

      <!-- MÉTODO PAGO -->
      <div class="checkout-block">
        <div class="checkout-section-title"><i class="fas fa-credit-card"></i> Método de Pago</div>
        <div class="payment-options">
          <label class="payment-card <?=($_POST['metodo_pago']??'')==='yape'?'selected':''?>">
            <input type="radio" name="metodo_pago" value="yape" <?=($_POST['metodo_pago']??'')==='yape'?'checked':''?> onchange="selectPayment(this)">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8e/Yape_logo.svg/120px-Yape_logo.svg.png" alt="Yape" style="height:22px;" onerror="this.outerHTML='<i class=\'fas fa-mobile-alt\' style=\'color:#6b2d8c;\'></i>'">
            <span>Yape</span>
          </label>
          <label class="payment-card <?=($_POST['metodo_pago']??'')==='plin'?'selected':''?>">
            <input type="radio" name="metodo_pago" value="plin" <?=($_POST['metodo_pago']??'')==='plin'?'checked':''?> onchange="selectPayment(this)">
            <i class="fas fa-mobile-alt" style="color:#00b0c8;font-size:20px;"></i><span>Plin</span>
          </label>
          <label class="payment-card <?=($_POST['metodo_pago']??'')==='transferencia'?'selected':''?>">
            <input type="radio" name="metodo_pago" value="transferencia" <?=($_POST['metodo_pago']??'')==='transferencia'?'checked':''?> onchange="selectPayment(this)">
            <i class="fas fa-university" style="color:var(--primary);font-size:20px;"></i><span>Transferencia</span>
          </label>
          <label class="payment-card <?=($_POST['metodo_pago']??'')==='tarjeta'?'selected':''?>">
            <input type="radio" name="metodo_pago" value="tarjeta" <?=($_POST['metodo_pago']??'')==='tarjeta'?'checked':''?> onchange="selectPayment(this)">
            <i class="fas fa-credit-card" style="color:var(--primary);font-size:20px;"></i><span>Tarjeta</span>
          </label>
        </div>
        <div id="yape-info" style="display:<?=in_array($_POST['metodo_pago']??'',['yape','plin'])?'block':'none'?>;margin-top:14px;background:rgba(107,45,140,.10);border:1px solid rgba(107,45,140,.25);border-radius:10px;padding:12px;font-size:13px;color:var(--gris2);">
          <i class="fas fa-info-circle" style="color:#9c27b0;"></i>
          Yape/Plin al: <strong>+51 950 923 109</strong> — Envía el comprobante por WhatsApp para confirmar.
        </div>
        <div id="transferencia-info" style="display:<?=($_POST['metodo_pago']??'')==='transferencia'?'block':'none'?>;margin-top:14px;background:rgba(215,224,34,.08);border:1px solid rgba(215,224,34,.25);border-radius:10px;padding:12px;font-size:13px;color:var(--gris2);">
          <i class="fas fa-university" style="color:#D7E022;"></i>
          <strong>BCP CCI:</strong> 00219100414817630152 — IP Tecnología Perú E.I.R.L.<br>
          <span style="color:var(--gris3);">Envía voucher a WhatsApp: <strong>+51 950 923 109</strong></span>
        </div>
      </div>

      <!-- NOTAS -->
      <div class="checkout-block">
        <div class="checkout-section-title"><i class="fas fa-ticket-alt"></i> Código de Cupón</div>
        <div class="form-group" style="margin-bottom:12px; display:flex; gap:10px; align-items:center;">
          <input type="text" name="cupon_codigo" value="<?= sanitize($_POST['cupon_codigo'] ?? $codigoCupon ?? '') ?>" placeholder="Ingresa tu código de cupón" style="flex:1;">
          <button type="submit" name="aplicar_cupon" class="btn-main" style="padding:10px 16px; white-space:nowrap;">Aplicar</button>
        </div>
        <?php if (!empty($cuponAplicado) && $descuentoCupon > 0): ?>
          <div style="background:rgba(76,175,80,.08);border:1px solid rgba(76,175,80,.25);border-radius:10px;padding:12px;font-size:13px;color:var(--gris2);">
            <i class="fas fa-check-circle" style="color:#4caf50;"></i>
            Cupón <strong><?= sanitize($cuponAplicado['codigo']) ?></strong> aplicado: descuento de <strong><?= formatPrice($descuentoCupon) ?></strong>.
          </div>
        <?php endif; ?>
      </div>

      <div class="checkout-block">
        <div class="checkout-section-title"><i class="fas fa-comment-alt"></i> Notas del pedido</div>
        <div class="form-group" style="margin-bottom:0;">
          <textarea name="notas" rows="3" placeholder="Indicaciones adicionales, horario preferido..."><?=sanitize($_POST['notas']??'')?></textarea>
        </div>
      </div>
    </div>

    <!-- RESUMEN -->
    <div>
      <div class="checkout-summary-wrap">
        <h3><i class="fas fa-receipt" style="color:#6b7300;"></i> Resumen del pedido</h3>
          <?php foreach($items as $it):
            $pr = $it['precio_oferta'] ?? $it['precio'];
            $pr = precioFinal($pr);
          ?>
        <div class="checkout-item">
          <div class="checkout-item-img">
            <?php if(!empty($it['imagen'])): ?><img src="<?=SITE_URL?>/<?=sanitize($it['imagen'])?>" alt="" style="width:100%;height:100%;object-fit:contain;padding:4px;">
            <?php else: ?><i class="fas <?=sanitize($it['icono'])?>"></i><?php endif; ?>
          </div>
          <div class="checkout-item-name"><?=sanitize($it['nombre'])?><span style="display:block;font-size:11px;color:var(--gris3);">x<?=$it['cantidad']?></span></div>
          <div class="checkout-item-price"><?=formatPrice($pr*$it['cantidad'])?></div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--borde);">
          <div class="summary-row">
            <span class="label">
              Subtotal
              <?php if ($descuentoCupon > 0): ?>
                <span style="font-size:12px;color:#4caf50;font-weight:600;display:inline-block;margin-left:6px;">(<?=($cuponAplicado['tipo_descuento'] ?? 'porcentaje') === 'monto' ? 'Descuento '.formatPrice($descuentoCupon) : sanitize($cuponAplicado['descuento']).'% de descuento'?>)</span>
              <?php endif; ?>
            </span>
            <span><?=formatPrice($subtotal)?></span>
          </div>
        <?php if ($descuentoCupon > 0): ?>
          <div class="summary-row" id="descuento-row"><span class="label">Descuento <?=sanitize($codigoCupon)?></span><span class="val" style="color:#4caf50;">-<?=formatPrice($descuentoCupon)?></span></div>
        <?php endif; ?>
          <div class="summary-row"><span class="label">Envío</span><span id="envio-val"><span style="color:var(--gris3);">Selecciona entrega</span></span></div>
          <div class="summary-row total"><span>Total</span><span class="val" id="total-val"><?=formatPrice($subtotal + ($envio ?? 0) - $descuentoCupon)?></span></div>
        </div>

        <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--borde);font-size:13px;color:var(--gris2);">
          <div style="display:flex;gap:8px;margin-bottom:6px;"><i class="fas fa-user" style="color:#6b7300;width:14px;"></i><span><?=sanitize($usuario['nombre'].' '.$usuario['apellido'])?></span></div>
          <div style="display:flex;gap:8px;margin-bottom:6px;"><i class="fas fa-envelope" style="color:#6b7300;width:14px;"></i><span><?=sanitize($usuario['email'])?></span></div>
          <?php if(!empty($usuario['celular'])): ?><div style="display:flex;gap:8px;"><i class="fas fa-phone" style="color:#6b7300;width:14px;"></i><span><?=sanitize($usuario['celular'])?></span></div><?php endif; ?>
        </div>

        <button type="submit" name="confirmar_pedido" class="btn-place-order"><i class="fas fa-lock"></i> Confirmar Pedido</button>
        <div style="margin-top:12px;text-align:center;font-size:12px;color:var(--gris3);"><i class="fas fa-shield-alt"></i> Compra 100% segura</div>
      </div>
    </div>
  </div>
  </form>
</div>

<script>
const SUBTOTAL=<?=$subtotal?>,COSTO_PROV=<?=$costoProvincias?>,CUpon_DESCUENTO=<?=$descuentoCupon?>;
const DISTRITOS=<?=json_encode($distritosLima)?>;
let tipoActual='<?=htmlspecialchars($_POST['tipo_envio']??'delivery')?>';

function updateResumen(){
  const ev=document.getElementById('envio-val'),tv=document.getElementById('total-val');
  let costo=0;
  if(tipoActual==='recojo_tienda'){ev.innerHTML='<span style="color:#4caf50;font-weight:700;">GRATIS</span>';} 
  else if(tipoActual==='provincia'){costo=COSTO_PROV;ev.textContent='S/ '+COSTO_PROV.toFixed(2);} 
  else{
    const sel=document.getElementById('select-distrito'),opt=sel?.options[sel.selectedIndex];
    if(opt&&opt.value){costo=parseFloat(opt.dataset.costo||0);ev.textContent='S/ '+costo.toFixed(2);} 
    else{ev.innerHTML='<span style="color:var(--gris3);">Elige distrito</span>';} 
  }
  const total = Math.max(0, SUBTOTAL + costo - CUpon_DESCUENTO);
  tv.textContent='S/ '+total.toFixed(2);
}

function switchEnvio(tipo){
  tipoActual=tipo;
  document.querySelectorAll('input[name="tipo_envio"]').forEach(r=>{r.closest('.option-card')?.classList.toggle('selected',r.value===tipo);});
  document.getElementById('fields-delivery').style.display=tipo==='delivery'?'':'none';
  document.getElementById('fields-provincia').style.display=tipo==='provincia'?'':'none';
  document.getElementById('fields-recojo').style.display=tipo==='recojo_tienda'?'':'none';
  updateResumen();
}

function updateCostoDistrito(v){
  const info=document.getElementById('costo-info'),txt=document.getElementById('costo-txt');
  if(v&&DISTRITOS[v]!==undefined){txt.textContent='S/ '+DISTRITOS[v].toFixed(2);info.style.display='block';}
  else info.style.display='none';
  updateResumen();
}

function selectPayment(r){
  document.querySelectorAll('.payment-card').forEach(c=>c.classList.remove('selected'));
  r.closest('.payment-card').classList.add('selected');
  document.getElementById('yape-info').style.display=(r.value==='yape'||r.value==='plin')?'block':'none';
  document.getElementById('transferencia-info').style.display=r.value==='transferencia'?'block':'none';
}

document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('input[name="tipo_envio"]').forEach(r=>{if(r.checked)switchEnvio(r.value);});
  document.querySelectorAll('.payment-card input[type="radio"]').forEach(r=>{if(r.checked)selectPayment(r);});
  const sd=document.getElementById('select-distrito');
  if(sd&&sd.value)updateCostoDistrito(sd.value);
  updateResumen();
});
</script>
<?php include 'includes/footer.php'; ?>
