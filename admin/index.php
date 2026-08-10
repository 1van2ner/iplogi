<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Panel Admin';
$pdo = getDB();

try {
    $col = $pdo->query("SHOW COLUMNS FROM productos LIKE 'canje_puntos'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec('ALTER TABLE productos ADD COLUMN canje_puntos INT NULL');
    } elseif (preg_match('/^tinyint/i', $col['Type'] ?? '')) {
        $pdo->exec('ALTER TABLE productos MODIFY COLUMN canje_puntos INT NULL');
    }
} catch (Exception $e) {
    // Si la tabla no tiene permiso o el campo ya existe, ignoramos.
}

$msg = ''; $msgType = 'success';

// ¿Ya se corrió migracion_roles.sql? (columna 'verificado' presente)
try {
    $colsUsuarios = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll(PDO::FETCH_COLUMN);
    $tieneVerificadoCol = in_array('verificado', $colsUsuarios);
    $tienePuntosCol = in_array('puntos', $colsUsuarios);
    if (!$tienePuntosCol) {
        $pdo->exec('ALTER TABLE usuarios ADD COLUMN puntos INT DEFAULT 0 AFTER activo');
        $tienePuntosCol = true;
    }
} catch (Exception $e) {
    $tieneVerificadoCol = false;
    $tienePuntosCol = false;
}

// ════════════════════════════════════════════════════════════
// ACCIONES POST
// ════════════════════════════════════════════════════════════

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Guardar producto ─────────────────────────────────────
    if ($action === 'guardar_producto') {
        $pid    = (int)($_POST['prod_id'] ?? 0);
        $nombre = sanitize(trim($_POST['nombre']        ?? ''));
        $catId  = (int)($_POST['categoria_id']          ?? 0);
        $marca  = sanitize(trim($_POST['marca']         ?? ''));
        $modelo = sanitize(trim($_POST['modelo']        ?? ''));
        $desc   = sanitize(trim($_POST['descripcion']   ?? ''));
        $precio = (float)($_POST['precio']              ?? 0);
        $oferta = (isset($_POST['precio_oferta']) && $_POST['precio_oferta'] !== '') ? (float)$_POST['precio_oferta'] : null;
        $stock  = sanitize(trim($_POST['stock']          ?? '0'));
        $espec     = sanitize(trim($_POST['especificaciones'] ?? ''));
        $dest      = isset($_POST['destacado']) ? 1 : 0;
        $activo    = isset($_POST['activo'])    ? 1 : 0;
        $canjear   = isset($_POST['canjear'])   ? 1 : 0;
        $puntosCanje = $canjear && trim($_POST['canje_puntos'] ?? '') !== ''
            ? max(0, (int)$_POST['canje_puntos'])
            : null;
        $imagen    = sanitize($_POST['imagen_actual']  ?? '');
        $imagen2   = sanitize($_POST['imagen2_actual'] ?? '');
        $potenciaVA = $_POST['potencia_va'] !== '' ? (int)$_POST['potencia_va'] : null;

        $dir = dirname(__DIR__) . '/assets/img/productos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (!empty($_FILES['imagen']['name'])) {
            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $nuevo = 'prod_'.time().'_'.rand(100,999).'.'.$ext;
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dir.$nuevo))
                    $imagen = 'assets/img/productos/'.$nuevo;
            }
        }
        if (!empty($_FILES['imagen2']['name'])) {
            $ext2 = strtolower(pathinfo($_FILES['imagen2']['name'], PATHINFO_EXTENSION));
            if (in_array($ext2, ['jpg','jpeg','png','webp','gif'])) {
                $nuevo2 = 'prod_'.(time()+1).'_'.rand(100,999).'_h.'.$ext2;
                if (move_uploaded_file($_FILES['imagen2']['tmp_name'], $dir.$nuevo2))
                    $imagen2 = 'assets/img/productos/'.$nuevo2;
            }
        }

        try {
            if ($pid > 0) {
                $pdo->prepare("UPDATE productos SET categoria_id=?,nombre=?,marca=?,modelo=?,descripcion=?,precio=?,precio_oferta=?,stock=?,especificaciones=?,destacado=?,activo=?,imagen=?,imagen2=?,potencia_va=?,canje_puntos=? WHERE id=?")
                    ->execute([$catId,$nombre,$marca,$modelo,$desc,$precio,$oferta,$stock,$espec,$dest,$activo,$imagen,$imagen2,$potenciaVA,$puntosCanje,$pid]);
            } else {
                $pdo->prepare("INSERT INTO productos (categoria_id,nombre,marca,modelo,descripcion,precio,precio_oferta,stock,especificaciones,destacado,activo,imagen,imagen2,potencia_va,canje_puntos) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$catId,$nombre,$marca,$modelo,$desc,$precio,$oferta,$stock,$espec,$dest,$activo,$imagen,$imagen2,$potenciaVA,$puntosCanje]);
            }
        } catch(Exception $e) {
            if ($pid > 0) {
                $pdo->prepare("UPDATE productos SET categoria_id=?,nombre=?,marca=?,modelo=?,descripcion=?,precio=?,precio_oferta=?,stock=?,especificaciones=?,destacado=?,activo=?,imagen=?,canje_puntos=? WHERE id=?")
                    ->execute([$catId,$nombre,$marca,$modelo,$desc,$precio,$oferta,$stock,$espec,$dest,$activo,$imagen,$puntosCanje,$pid]);
            } else {
$pdo->prepare("INSERT INTO productos (categoria_id, nombre, marca, modelo, descripcion, precio, precio_oferta, stock, especificaciones, destacado, activo, imagen, canje_puntos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([$catId, $nombre, $marca, $modelo, $desc, $precio, $oferta, $stock, $espec, $dest, $activo, $imagen, $puntosCanje]);
            }
        }
        $msg = $pid > 0 ? "Producto \"$nombre\" actualizado." : "Producto \"$nombre\" creado.";
    }

    // ── Eliminar/desactivar producto ─────────────────────────
    elseif ($action === 'eliminar_producto') {
        $pid = (int)($_POST['prod_id'] ?? 0);
        if ($pid) {
            $pdo->prepare("DELETE FROM productos WHERE id=?")->execute([$pid]);
            $msg = 'Producto eliminado permanentemente.';
        }
    }

    // ── Crear usuario ─────────────────────────────────────────
    elseif ($action === 'guardar_usuario') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $fdoc = in_array($_POST['tipo_documento']??'',['DNI','RUC']) ? $_POST['tipo_documento'] : 'DNI';
        $dni  = sanitize($_POST['dni_ruc']           ?? '');
        $nom  = sanitize($_POST['u_nombre']          ?? '');
        $ape  = sanitize($_POST['u_apellido']        ?? '');
        $email= sanitize($_POST['u_email']           ?? '');
        $cel  = sanitize($_POST['u_celular']         ?? '');
        $dir  = sanitize($_POST['u_direccion']       ?? '');
        $fnac = sanitize($_POST['u_fnac']            ?? '');
        $rol  = in_array($_POST['u_rol']??'',['admin','cliente_final','tecnico','proyectista','distribuidor','motorizado']) ? $_POST['u_rol'] : 'cliente_final';
        $acti = isset($_POST['u_activo']) ? 1 : 0;
        $verif = isset($_POST['u_verificado']) ? 1 : 0;
        $puntos = max(0, (int)($_POST['u_puntos'] ?? 0));
        $pass = $_POST['u_pass'] ?? '';

        if ($uid > 0) {
            $verifSql = $tieneVerificadoCol ? ",verificado=?,verificado_en=" . ($verif ? "NOW()" : "NULL") : "";
            if (!empty($pass)) {
                $pdo->prepare("UPDATE usuarios SET tipo_documento=?,dni_ruc=?,nombre=?,apellido=?,email=?,telefono=?,celular=?,direccion_entrega=?,fecha_nacimiento=?,rol=?,activo=?,puntos=?$verifSql,password=? WHERE id=?")
                    ->execute($tieneVerificadoCol
                        ? [$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,$rol,$acti,$puntos,$verif,password_hash($pass,PASSWORD_DEFAULT),$uid]
                        : [$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,$rol,$acti,$puntos,password_hash($pass,PASSWORD_DEFAULT),$uid]);
            } else {
                $pdo->prepare("UPDATE usuarios SET tipo_documento=?,dni_ruc=?,nombre=?,apellido=?,email=?,telefono=?,celular=?,direccion_entrega=?,fecha_nacimiento=?,rol=?,activo=?,puntos=?$verifSql WHERE id=?")
                    ->execute($tieneVerificadoCol
                        ? [$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,$rol,$acti,$puntos,$verif,$uid]
                        : [$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,$rol,$acti,$puntos,$uid]);
            }
            $msg = "Usuario \"$nom\" actualizado.";
        } else {
            $formErr = [];
            if ($fdoc==='DNI' && !preg_match('/^\d{8}$/',$dni)) $formErr[]='DNI debe tener 8 dígitos.';
            if ($fdoc==='RUC' && !preg_match('/^\d{11}$/',$dni)) $formErr[]='RUC debe tener 11 dígitos.';
            if (strlen($nom)<2)  $formErr[]='Ingresa el nombre.';
            if (strlen($ape)<2)  $formErr[]='Ingresa el apellido.';
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) $formErr[]='Correo no válido.';
            if (!preg_match('/^[\d\s\+\-]{7,15}$/',$cel)) $formErr[]='Celular no válido.';
            if (strlen($pass)<6) $formErr[]='Contraseña mínimo 6 caracteres.';

            if (empty($formErr)) {
                $s=$pdo->prepare("SELECT id FROM usuarios WHERE email=?"); $s->execute([$email]);
                if ($s->fetch()) $formErr[]='Este correo ya está registrado.';
                $s=$pdo->prepare("SELECT id FROM usuarios WHERE dni_ruc=?"); $s->execute([$dni]);
                if ($s->fetch()) $formErr[]='Este '.$fdoc.' ya está registrado.';
            }
            if ($formErr) { $msg = implode(' / ',$formErr); $msgType='error'; }
            else {
                if ($tieneVerificadoCol) {
                    $pdo->prepare("INSERT INTO usuarios (tipo_documento,dni_ruc,nombre,apellido,email,telefono,celular,direccion_entrega,fecha_nacimiento,password,rol,activo,puntos,verificado,verificado_en) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,?,".($verif?"NOW()":"NULL").")")
                        ->execute([$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,password_hash($pass,PASSWORD_DEFAULT),$rol,$puntos,$verif]);
                } else {
                    $pdo->prepare("INSERT INTO usuarios (tipo_documento,dni_ruc,nombre,apellido,email,telefono,celular,direccion_entrega,fecha_nacimiento,password,rol,activo,puntos) VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)")
                        ->execute([$fdoc,$dni,$nom,$ape,$email,$cel,$cel,$dir?:null,$fnac?:null,password_hash($pass,PASSWORD_DEFAULT),$rol,$puntos]);
                }
                $msg = "Usuario \"$nom $ape\" creado correctamente.";
            }
        }
    }

    // ── Toggle check de verificación (un clic) ────────────────
    elseif ($action === 'toggle_verificado' && $tieneVerificadoCol) {
        $uid = (int)($_POST['user_id'] ?? 0);
        $s = $pdo->prepare("SELECT verificado FROM usuarios WHERE id=?");
        $s->execute([$uid]);
        $actual = (int)$s->fetchColumn();
        $nuevo = $actual ? 0 : 1;
        $pdo->prepare("UPDATE usuarios SET verificado=?, verificado_en=" . ($nuevo ? "NOW()" : "NULL") . " WHERE id=?")
            ->execute([$nuevo, $uid]);
        $msg = $nuevo ? 'Usuario marcado como verificado.' : 'Verificación retirada.';
    }

    // ── Eliminar usuario ─────────────────────────────────────
    elseif ($action === 'eliminar_usuario') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['usuario_id']) {
            $pdo->prepare("DELETE FROM usuarios WHERE id=?")->execute([$uid]);
            $msg = 'Usuario eliminado.';
        } else { $msg = 'No puedes eliminarte a ti mismo.'; $msgType='error'; }
    }
}

// ════════════════════════════════════════════════════════════
// DATOS
// ════════════════════════════════════════════════════════════
$s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
$s->execute([$_SESSION['usuario_id']]);
$adminUser = $s->fetch();

$totalPedidos   = (int)$pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$pedidosPend    = (int)$pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='pendiente'")->fetchColumn();
$totalProductos = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1")->fetchColumn();
$totalUsuarios  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol != 'admin'")->fetchColumn();
$ingresosTotal  = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado NOT IN ('cancelado')")->fetchColumn();
$ingresosMes    = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado NOT IN ('cancelado') AND MONTH(creado_en)=MONTH(NOW()) AND YEAR(creado_en)=YEAR(NOW())")->fetchColumn();
$stockBajo      = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE stock <= 5 AND activo=1")->fetchColumn();
$totalCupones   = (int)$pdo->query("SELECT COUNT(*) FROM cupones")->fetchColumn();

$ultPedidos = $pdo->query("SELECT p.*,u.nombre,u.apellido FROM pedidos p JOIN usuarios u ON p.usuario_id=u.id ORDER BY p.creado_en DESC LIMIT 8")->fetchAll();
$topVentas  = $pdo->query("SELECT p.nombre,p.marca,c.icono,SUM(dp.cantidad) as total_vendido,SUM(dp.subtotal) as ingresos FROM detalle_pedidos dp JOIN productos p ON dp.producto_id=p.id JOIN categorias c ON p.categoria_id=c.id JOIN pedidos ped ON dp.pedido_id=ped.id WHERE ped.estado!='cancelado' GROUP BY p.id ORDER BY total_vendido DESC LIMIT 5")->fetchAll();
$prodStockBajo = $pdo->query("SELECT p.*,c.nombre as cat_nombre,c.icono FROM productos p JOIN categorias c ON p.categoria_id=c.id WHERE p.stock<=5 AND p.activo=1 ORDER BY p.stock ASC LIMIT 6")->fetchAll();

$qProd  = sanitize($_GET['qprod'] ?? '');
$catFil = (int)($_GET['cat'] ?? 0);
$wP=[]; $pP=[];
if ($qProd)  { $wP[]="(p.nombre LIKE ? OR p.marca LIKE ?)"; $pP[]="%$qProd%"; $pP[]="%$qProd%"; }
if ($catFil) { $wP[]="p.categoria_id=?"; $pP[]=$catFil; }
$wsP = $wP ? 'WHERE '.implode(' AND ',$wP) : '';
$stmP = $pdo->prepare("SELECT p.*,c.nombre as cat_nombre,c.icono FROM productos p JOIN categorias c ON p.categoria_id=c.id $wsP ORDER BY p.id DESC LIMIT 100");
$stmP->execute($pP); $productos = $stmP->fetchAll();
$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre")->fetchAll();

$qUser = sanitize($_GET['quser'] ?? '');
$rolFil= sanitize($_GET['rfil']  ?? '');
$wU=[]; $pU=[];
if ($qUser)  { $wU[]='(nombre LIKE ? OR apellido LIKE ? OR email LIKE ? OR dni_ruc LIKE ?)'; for($i=0;$i<4;$i++) $pU[]="%$qUser%"; }
if ($rolFil) { $wU[]='rol=?'; $pU[]=$rolFil; }
$wsU = $wU ? 'WHERE '.implode(' AND ',$wU) : '';
$stmU = $pdo->prepare("SELECT u.*,(SELECT COUNT(*) FROM pedidos WHERE usuario_id=u.id) as n_pedidos FROM usuarios u $wsU ORDER BY u.creado_en DESC");
$stmU->execute($pU); $usuariosList = $stmU->fetchAll();

$pedidosAdmin = $pdo->query("SELECT p.*,u.nombre,u.apellido,u.email FROM pedidos p JOIN usuarios u ON p.usuario_id=u.id ORDER BY p.creado_en DESC LIMIT 50")->fetchAll();

$editProd = null;
if (isset($_GET['editprod'])) { $ep=$pdo->prepare("SELECT * FROM productos WHERE id=?"); $ep->execute([(int)$_GET['editprod']]); $editProd=$ep->fetch(); }
$editUser = null;
if (isset($_GET['edituser'])) { $eu=$pdo->prepare("SELECT * FROM usuarios WHERE id=?"); $eu->execute([(int)$_GET['edituser']]); $editUser=$eu->fetch(); }

$tab = $_GET['tab'] ?? 'dashboard';
include '../includes/header.php';
?>

<style>
:root{ --amarillo-texto:#6b7300; } /* variante oscura del amarillo, para texto legible sobre fondo claro */
.admin-layout{display:grid;grid-template-columns:260px 1fr;gap:24px;padding:28px 0 60px;align-items:start;}
.admin-layout > main{min-width:0;}
.admin-sidebar{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);padding:24px;position:sticky;top:150px;}
.admin-avatar{width:80px;height:80px;background:var(--amarillo);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:34px;color:#000;margin:0 auto 14px;box-shadow:0 0 0 4px rgba(237,232,42,.25);}
.admin-name{text-align:center;font-size:17px;font-weight:900;color:var(--blanco);margin-bottom:4px;}
.admin-role{text-align:center;margin-bottom:6px;}
.admin-email{text-align:center;font-size:12px;color:var(--gris3);margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--borde);}
.admin-nav a{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:var(--r);font-size:13px;color:var(--gris2);transition:all .2s;margin-bottom:3px;text-decoration:none;font-weight:600;}
.admin-nav a:hover{background:rgba(237,232,42,.1);color:var(--amarillo-texto);}
.admin-nav a.active{background:rgba(237,232,42,.15);color:var(--amarillo-texto);border-left:3px solid var(--amarillo);padding-left:9px;}
.admin-nav a i{width:18px;color:var(--amarillo-texto);font-size:14px;}
.admin-nav .sep{height:1px;background:var(--borde);margin:10px 0;}
.admin-stat-mini{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin:16px 0;}
.mini-stat{background:var(--bg3);border-radius:8px;padding:10px;text-align:center;}
.mini-stat .v{font-size:18px;font-weight:900;color:var(--amarillo-texto);}
.mini-stat .l{font-size:10px;color:var(--gris3);margin-top:1px;}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:24px;}
.stat-card{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);padding:16px 18px;text-decoration:none;display:block;transition:all .2s;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--sc);}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.4);border-color:var(--sc);}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;color:var(--sc);background:color-mix(in srgb,var(--sc) 15%,transparent);margin-bottom:10px;}
.stat-label{font-size:10px;font-weight:700;color:var(--gris3);text-transform:uppercase;letter-spacing:.5px;}
.stat-val{font-size:20px;font-weight:900;color:var(--blanco);margin-top:2px;}
.dash-panel{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);overflow:hidden;margin-bottom:20px;}
.dash-panel-head{display:flex;justify-content:space-between;align-items:center;padding:13px 18px;background:var(--bg3);border-bottom:1px solid var(--borde);}
.dash-panel-head h3{font-size:14px;font-weight:800;color:var(--blanco);margin:0;display:flex;align-items:center;gap:7px;}
.dash-panel-head a{font-size:12px;color:var(--amarillo-texto);text-decoration:none;font-weight:700;}
.ped-row{display:flex;justify-content:space-between;align-items:center;padding:10px 18px;border-bottom:1px solid var(--borde);font-size:13px;transition:background .15s;}
.ped-row:last-child{border-bottom:none;}
.ped-row:hover{background:var(--bg3);}
.ped-code{font-weight:800;color:var(--amarillo-texto);}
.ped-name{font-size:11px;color:var(--gris3);margin-top:1px;}
.top-row{display:flex;align-items:center;gap:12px;padding:10px 18px;border-bottom:1px solid var(--borde);font-size:13px;}
.top-row:last-child{border-bottom:none;}
.top-num{width:26px;height:26px;background:var(--amarillo);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:900;color:#000;flex-shrink:0;}
.top-icon{width:34px;height:34px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--amarillo-texto);flex-shrink:0;}
.atbl{width:100%;border-collapse:collapse;font-size:13px;}
.atbl th{padding:10px 14px;text-align:left;font-weight:700;color:var(--gris3);background:var(--bg3);border-bottom:1px solid var(--borde);white-space:nowrap;}
.atbl td{padding:10px 14px;border-bottom:1px solid var(--borde);color:var(--gris2);vertical-align:middle;}
.atbl tr:last-child td{border-bottom:none;}
.atbl tr:hover td{background:rgba(255,255,255,.025);}
.col-sticky-actions{position:sticky;right:0;background:var(--bg2);box-shadow:-6px 0 8px -6px rgba(0,0,0,.15);z-index:2;}
.atbl tr:hover .col-sticky-actions{background:#fafafa;}
.btn-sm{padding:5px 11px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:5px;text-decoration:none;transition:all .15s;}
.btn-edit{background:rgba(237,232,42,.12);color:var(--amarillo-texto);}
.btn-edit:hover{background:var(--amarillo);color:#000;}
.btn-del{background:rgba(229,57,53,.1);color:#ff6b6b;}
.btn-del:hover{background:var(--rojo);color:#fff;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:2000;align-items:center;justify-content:center;padding:20px;}
.modal-overlay.open{display:flex;}
.modal-box{background:var(--bg2);border:1.5px solid var(--borde);border-radius:var(--rl);padding:28px;width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.6);position:relative;}
.modal-title{font-size:18px;font-weight:900;color:var(--blanco);margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--borde);display:flex;align-items:center;gap:8px;}
.modal-close{position:absolute;top:16px;right:16px;background:var(--bg3);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;color:var(--gris3);}
.modal-close:hover{background:var(--rojo);color:#fff;}
.sk-ok{background:rgba(67,160,71,.2);color:#81c784;}
.sk-low{background:rgba(245,124,0,.2);color:#ffb74d;}
.sk-out{background:rgba(229,57,53,.2);color:#ff6b6b;}
.eb{padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;}
.eb-pendiente{background:rgba(245,124,0,.2);color:#ffb74d;}
.eb-confirmado{background:rgba(2,136,209,.2);color:#4fc3f7;}
.eb-procesando{background:rgba(237,232,42,.15);color:var(--amarillo-texto);}
.eb-enviado{background:rgba(67,160,71,.2);color:#81c784;}
.eb-entregado{background:rgba(67,160,71,.25);color:#a5d6a7;}
.eb-cancelado{background:rgba(229,57,53,.2);color:#ff6b6b;}
.sbar{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
.sbar input,.sbar select{padding:8px 12px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);font-size:13px;color:var(--blanco);}
.sbar input:focus,.sbar select:focus{border-color:var(--amarillo);outline:none;}
/* Formularios ocultos para eliminar (sin display visible) */
.form-del-hidden{display:none;}
@media(max-width:1000px){
  .admin-layout{grid-template-columns:1fr;}
  .admin-sidebar{position:static;}

  /* Info de perfil en fila */
  .admin-avatar{width:50px;height:50px;font-size:22px;float:left;margin:0 12px 0 0;}
  .admin-name{text-align:left;font-size:14px;}
  .admin-role{text-align:left;}
  .admin-email{text-align:left;margin-bottom:10px;padding-bottom:10px;}
  .admin-stat-mini{grid-template-columns:repeat(4,1fr);}

  /* Nav horizontal scrolleable */
  .admin-nav{
    display:flex;
    flex-wrap:nowrap;
    overflow-x:auto;
    gap:4px;
    padding-bottom:6px;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:none;
  }
  .admin-nav::-webkit-scrollbar{display:none;}
  .admin-nav a{
    flex:0 0 auto;
    white-space:nowrap;
    justify-content:center;
    text-align:center;
    padding:8px 12px;
    font-size:12px;
  }
  .admin-nav .sep{display:none;}
  .stat-grid{grid-template-columns:repeat(2,1fr);}
  .two-cols{grid-template-columns:1fr !important;}
}

@media(max-width:600px){
  .admin-stat-mini{grid-template-columns:repeat(2,1fr);}
  .admin-nav a span{display:inline !important;} /* Mostrar texto en nav */
  .dash-panel > div{overflow-x:auto;}
  .atbl{min-width:550px;}
}
  @media(max-width:900px){
  .user-btn .admin-name-header{display:inline !important;}
}
/* ══════════════════════════════════════════
   FIX GLOBAL — NOMBRE USUARIO + WHATSAPP
   ══════════════════════════════════════════ */

/* 1. Nombre siempre visible en header */
.user-btn span {
  display: inline !important;
  max-width: 90px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

/* 2. WhatsApp widget — no salirse de pantalla */
#wsp-widget {
  position: fixed !important;
  bottom: 20px !important;
  right: 16px !important;
  z-index: 9999 !important;
  /* Ancla al viewport, nada puede empujarlo */
  max-width: calc(100vw - 32px) !important;
  /* Evita que el menú empuje el scroll horizontal */
  overflow: visible !important;
}

#wsp-toggle {
  width: 54px !important;
  height: 54px !important;
  border-radius: 50% !important;
  display: flex !important;
  align-items: center !important;
  justify-content: center !important;
  flex-shrink: 0 !important;
  /* El badge no se recorta */
  overflow: visible !important;
  margin-left: auto !important;
}

/* El menú desplegable nunca sale por la derecha */
#wsp-menu {
  position: absolute !important;
  bottom: 66px !important;
  right: 0 !important;
  width: 300px !important;
  max-width: calc(100vw - 32px) !important;
  box-sizing: border-box !important;
}

@media (max-width: 480px) {
  #wsp-widget {
    bottom: 14px !important;
    right: 10px !important;
  }
  #wsp-menu {
    width: calc(100vw - 20px) !important;
    /* Se alinea al borde izquierdo de la pantalla */
    right: auto !important;
    left: calc(-100vw + 74px) !important;
  }
}
</style>

<div class="container">
<div class="admin-layout">

  <!-- SIDEBAR -->
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
      <div class="mini-stat"><div class="v" style="color:<?= $pedidosPend>0?'#ffb74d':'var(--amarillo-texto)' ?>;"><?= $pedidosPend ?></div><div class="l">Pendientes</div></div>
    </div>
    <nav class="admin-nav">
      <a href="?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
      <a href="?tab=productos"  class="<?= $tab==='productos' ?'active':'' ?>"><i class="fas fa-box"></i> Productos</a>
      <a href="?tab=usuarios"   class="<?= $tab==='usuarios'  ?'active':'' ?>"><i class="fas fa-users"></i> Usuarios</a>
      <a href="promociones.php"><i class="fas fa-fire"></i> Promociones del Mes</a>
      <a href="cupon.php" class="<?= $activeMenu === 'cupones' ? 'active' : '' ?>">
            <i class="fas fa-ticket"></i> Cupones
      </a>
      <a href="eventos.php" class="<?= $activeMenu === 'eventos' ? 'active' : '' ?>">
        <i class="fas fa-calendar-day"></i> Eventos
      </a>
      <a href="?tab=pedidos"    class="<?= $tab==='pedidos'   ?'active':'' ?>">
        <i class="fas fa-shopping-bag"></i> Pedidos
        <?php if($pedidosPend>0): ?>
          <span style="margin-left:auto;background:var(--rojo);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:10px;"><?= $pedidosPend ?></span>
        <?php endif; ?>
      </a>
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

  <!-- CONTENIDO PRINCIPAL -->
  <main>

    <?php if($msg): ?>
    <div class="alert alert-<?= $msgType==='error'?'error':'success' ?>" style="margin-bottom:20px;">
      <i class="fas fa-<?= $msgType==='error'?'times-circle':'check-circle' ?>"></i> <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- ── DASHBOARD ───────────────────────────────────────── -->
    <?php if ($tab === 'dashboard'): ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
      <div>
        <h1 style="font-size:22px;font-weight:900;color:var(--blanco);"><i class="fas fa-tachometer-alt" style="color:var(--amarillo-texto);margin-right:8px;"></i>Dashboard</h1>
        <p style="color:var(--gris3);font-size:13px;margin-top:3px;"><?= date('d/m/Y H:i') ?> — Todo bajo control</p>
      </div>
      <a href="?tab=productos&nuevo=1" style="padding:9px 18px;background:var(--amarillo);color:#000;border-radius:var(--r);font-size:13px;font-weight:800;text-decoration:none;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> Nuevo Producto
      </a>
    </div>

    <div class="stat-grid">
      <?php
      $cards=[
        ['fas fa-shopping-bag','Total Pedidos',$totalPedidos,'#CEFF04','?tab=pedidos'],
        ['fas fa-clock','Pendientes',$pedidosPend,'#ff9800','?tab=pedidos'],
        ['fas fa-box','Productos',$totalProductos,'#29b6f6','?tab=productos'],
        ['fas fa-users','Clientes',$totalUsuarios,'#66bb6a','?tab=usuarios'],
        ['fas fa-dollar-sign','Ingresos Total',formatPrice($ingresosTotal),'#CEFF04','reportes.php'],
        ['fas fa-calendar-alt','Ingr. del Mes',formatPrice($ingresosMes),'#29b6f6','reportes.php'],
        ['fas fa-ticket','Cupones',$totalCupones,'#a478ff','cupon.php'],
        ['fas fa-exclamation-triangle','Stock Bajo',$stockBajo.' prods','#ef5350','?tab=productos'],
      ];
      foreach($cards as [$ico,$lbl,$val,$col,$link]):
      ?><a href="<?= $link ?>" class="stat-card" style="--sc:<?= $col ?>;">
        <div class="stat-icon"><i class="<?= $ico ?>"></i></div>
        <div class="stat-label"><?= $lbl ?></div>
        <div class="stat-val"><?= $val ?></div>
      </a><?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="two-cols">
      <div class="dash-panel">
        <div class="dash-panel-head">
          <h3><i class="fas fa-shopping-bag" style="color:var(--amarillo-texto);"></i> Últimos pedidos</h3>
          <a href="?tab=pedidos">Ver todos →</a>
        </div>
        <?php if(empty($ultPedidos)): ?>
          <div style="padding:32px;text-align:center;color:var(--gris3);">No hay pedidos aún</div>
        <?php else: foreach($ultPedidos as $ped): $bc='eb-'.$ped['estado']; ?>
        <div class="ped-row">
          <div>
            <div class="ped-code">#<?= str_pad($ped['id'],6,'0',STR_PAD_LEFT) ?></div>
            <div class="ped-name"><?= sanitize($ped['nombre'].' '.$ped['apellido']) ?></div>
          </div>
          <div style="text-align:right;">
            <span class="eb <?= $bc ?>"><?= ucfirst($ped['estado']) ?></span>
            <div style="font-size:13px;font-weight:800;color:var(--amarillo-texto);margin-top:3px;"><?= formatPrice($ped['total']) ?></div>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <div class="dash-panel">
        <div class="dash-panel-head">
          <h3><i class="fas fa-fire" style="color:var(--amarillo-texto);"></i> Más vendidos</h3>
          <a href="reportes.php">Ver reporte →</a>
        </div>
        <?php if(empty($topVentas)): ?>
          <div style="padding:32px;text-align:center;color:var(--gris3);">Sin datos aún</div>
        <?php else: foreach($topVentas as $i=>$p): ?>
        <div class="top-row">
          <div class="top-num"><?= $i+1 ?></div>
          <div class="top-icon"><i class="fas <?= sanitize($p['icono']) ?>"></i></div>
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;color:var(--blanco);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($p['nombre']) ?></div>
            <div style="font-size:11px;color:var(--gris3);"><?= sanitize($p['marca']) ?> · <?= $p['total_vendido'] ?> uds</div>
          </div>
          <div style="font-weight:800;color:var(--amarillo-texto);flex-shrink:0;"><?= formatPrice($p['ingresos']) ?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <?php if(!empty($prodStockBajo)): ?>
    <div class="dash-panel" style="border-color:rgba(229,57,53,.4);">
      <div class="dash-panel-head" style="background:rgba(229,57,53,.08);border-bottom-color:rgba(229,57,53,.3);">
        <h3 style="color:#ff6b6b;"><i class="fas fa-exclamation-triangle"></i> Stock bajo (≤ 5 unidades)</h3>
        <a href="?tab=productos" style="color:#ff6b6b;">Gestionar →</a>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1px;background:var(--borde);">
        <?php foreach($prodStockBajo as $p): ?>
        <div style="background:var(--bg2);padding:14px 16px;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <div style="width:34px;height:34px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:15px;color:var(--amarillo-texto);"><i class="fas <?= sanitize($p['icono']) ?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12px;font-weight:700;color:var(--blanco);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= sanitize($p['nombre']) ?></div>
              <div style="font-size:11px;color:var(--gris3);"><?= sanitize($p['cat_nombre']) ?></div>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="background:rgba(229,57,53,.2);color:#ff6b6b;font-size:11px;font-weight:700;padding:2px 8px;border-radius:8px;">Stock: <?= $p['stock'] ?></span>
            <span style="font-size:12px;font-weight:800;color:var(--amarillo-texto);"><?= formatPrice($p['precio']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── PRODUCTOS ───────────────────────────────────────── -->
    <?php elseif ($tab === 'productos'): ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
      <h2 style="font-size:20px;font-weight:900;color:var(--blanco);"><i class="fas fa-box" style="color:var(--amarillo-texto);"></i> Gestión de Productos</h2>
      <button onclick="abrirModalProd(null)" style="padding:9px 18px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-plus"></i> Nuevo Producto
      </button>
    </div>

    <form method="GET" class="sbar">
      <input type="hidden" name="tab" value="productos">
      <input type="text" name="qprod" value="<?= $qProd ?>" placeholder="Buscar producto..." style="flex:1;min-width:180px;">
      <select name="cat">
        <option value="">Todas las categorías</option>
        <?php foreach($categorias as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $catFil==$c['id']?'selected':'' ?>><?= sanitize($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" style="padding:8px 16px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:13px;font-weight:800;cursor:pointer;"><i class="fas fa-search"></i></button>
      <a href="?tab=productos" style="padding:8px 12px;background:var(--bg3);color:var(--gris2);border:1px solid var(--borde);border-radius:var(--r);font-size:13px;text-decoration:none;">Limpiar</a>
    </form>

    <div class="dash-panel">
      <div class="dash-panel-head">
        <h3><i class="fas fa-box" style="color:var(--amarillo-texto);"></i> <?= count($productos) ?> productos</h3>
      </div>
      <div style="overflow-x:auto;">
        <table class="atbl">
          <thead><tr><th>Img</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
          <tbody>
          <?php foreach($productos as $pr):
            $sk = (empty($pr['stock']) || $pr['stock']==='0' || strtolower($pr['stock'])==='agotado') ? 'sk-out' : (preg_match('/^[1-5][+\-]?$/', trim($pr['stock'])) ? 'sk-low' : 'sk-ok');
          ?>
          <tr>
            <td><?php if(!empty($pr['imagen'])): ?>
              <img src="<?= SITE_URL ?>/<?= sanitize($pr['imagen']) ?>" style="width:42px;height:42px;border-radius:8px;object-fit:cover;">
            <?php else: ?>
              <div style="width:42px;height:42px;background:var(--bg3);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--amarillo-texto);"><i class="fas <?= sanitize($pr['icono']??'fa-box') ?>"></i></div>
            <?php endif; ?></td>
            <td>
              <div style="font-weight:700;color:var(--blanco);"><?= sanitize($pr['nombre']) ?></div>
              <div style="font-size:11px;color:var(--gris3);"><?= sanitize($pr['marca']) ?> <?= sanitize($pr['modelo']??'') ?></div>
            </td>
            <td style="font-size:12px;color:var(--gris3);"><?= sanitize($pr['cat_nombre']) ?></td>
            <td>
              <div style="font-weight:800;color:var(--amarillo-texto);"><?= formatPrice($pr['precio']) ?></div>
              <?php if($pr['precio_oferta']): ?><div style="font-size:11px;color:#ff6b6b;">Oferta: <?= formatPrice($pr['precio_oferta']) ?></div><?php endif; ?>
            </td>
            <td><span class="btn-sm <?= $sk ?>"><?= $pr['stock'] ?> uds</span></td>
            <td>
              <span style="padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700;background:<?= $pr['activo']?'rgba(67,160,71,.2)':'rgba(229,57,53,.2)' ?>;color:<?= $pr['activo']?'#81c784':'#ff6b6b' ?>;">
                <?= $pr['activo']?'Activo':'Inactivo' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;gap:5px;flex-wrap:wrap;">
                <button class="btn-sm btn-edit" onclick='abrirModalProd(<?= htmlspecialchars(json_encode($pr),ENT_QUOTES) ?>)'>
                  <i class="fas fa-edit"></i> Editar
                </button>
                <!-- Formulario oculto para eliminar producto -->
                <form id="form-delprod-<?= $pr['id'] ?>" method="POST" action="?tab=productos" class="form-del-hidden">
                  <input type="hidden" name="action" value="eliminar_producto">
                  <input type="hidden" name="prod_id" value="<?= $pr['id'] ?>">
                </form>
                <button type="button" class="btn-sm btn-del"
                        onclick="eliminarProducto(<?= $pr['id'] ?>, '<?= htmlspecialchars(addslashes($pr['nombre']),ENT_QUOTES) ?>')">
                  <i class="fas fa-trash"></i> Eliminar
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($productos)): ?><tr><td colspan="7" style="text-align:center;padding:40px;color:var(--gris3);">No hay productos</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── USUARIOS ────────────────────────────────────────── -->
    <?php elseif ($tab === 'usuarios'): ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
      <h2 style="font-size:20px;font-weight:900;color:var(--blanco);"><i class="fas fa-users" style="color:var(--amarillo-texto);"></i> Gestión de Usuarios</h2>
      <button onclick="abrirModalUser(null)" style="padding:9px 18px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:13px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;">
        <i class="fas fa-user-plus"></i> Nuevo Usuario
      </button>
    </div>

    <form method="GET" class="sbar">
      <input type="hidden" name="tab" value="usuarios">
      <input type="text" name="quser" value="<?= $qUser ?>" placeholder="Buscar por nombre, email, DNI..." style="flex:1;min-width:200px;">
      <select name="rfil">
        <option value="">Todos</option>
        <option value="cliente_final" <?= $rolFil==='cliente_final'?'selected':'' ?>>Cliente Final</option>
        <option value="tecnico"       <?= $rolFil==='tecnico'      ?'selected':'' ?>>Técnico</option>
        <option value="proyectista"   <?= $rolFil==='proyectista'  ?'selected':'' ?>>Proyectista</option>
        <option value="distribuidor"  <?= $rolFil==='distribuidor' ?'selected':'' ?>>Distribuidor</option>
        <option value="motorizado"    <?= $rolFil==='motorizado'   ?'selected':'' ?>>Motorizado</option>
        <option value="admin"         <?= $rolFil==='admin'        ?'selected':'' ?>>Admins</option>
      </select>
      <button type="submit" style="padding:8px 16px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:13px;font-weight:800;cursor:pointer;"><i class="fas fa-search"></i></button>
      <a href="?tab=usuarios" style="padding:8px 12px;background:var(--bg3);color:var(--gris2);border:1px solid var(--borde);border-radius:var(--r);font-size:13px;text-decoration:none;">Limpiar</a>
    </form>

    <div class="dash-panel">
      <div class="dash-panel-head">
        <h3><i class="fas fa-users" style="color:var(--amarillo-texto);"></i> <?= count($usuariosList) ?> usuarios</h3>
      </div>
      <div style="overflow-x:auto;">
        <table class="atbl">
          <thead><tr><th>#</th><th>Documento</th><th>Nombre</th><th>Correo</th><th>Celular</th><th>Rol</th><th>Verificado</th><th>Pedidos</th><th>Estado</th><th class="col-sticky-actions">Acciones</th></tr></thead>
          <tbody>
          <?php foreach($usuariosList as $u): $cel=$u['celular']??$u['telefono']??''; ?>
          <tr style="<?= !$u['activo']?'opacity:.4;':'' ?>">
            <td style="color:var(--gris3);">#<?= $u['id'] ?></td>
            <td style="white-space:nowrap;">
              <?php if(!empty($u['dni_ruc'])): ?>
                <span style="font-size:10px;font-weight:800;background:<?= ($u['tipo_documento']??'DNI')==='RUC'?'rgba(2,136,209,.2)':'rgba(237,232,42,.15)' ?>;color:<?= ($u['tipo_documento']??'DNI')==='RUC'?'#4fc3f7':'var(--amarillo-texto)' ?>;padding:2px 6px;border-radius:4px;margin-right:4px;"><?= $u['tipo_documento']??'DNI' ?></span>
                <strong style="color:var(--blanco);"><?= sanitize($u['dni_ruc']) ?></strong>
              <?php else: ?><span style="color:var(--gris3);">—</span><?php endif; ?>
            </td>
            <td style="max-width:170px;"><div style="font-weight:600;font-size:13px;line-height:1.4;color:var(--gris1);"><?= sanitize(trim($u['nombre'].' '.($u['apellido']??''))) ?></div></td>
            <td><a href="mailto:<?= sanitize($u['email']) ?>" style="color:var(--amarillo-texto);font-size:12px;font-weight:600;text-decoration:underline;"><?= sanitize($u['email']) ?></a></td>
            <td style="font-size:12px;">
              <?php if($cel): ?>
                <a href="https://wa.me/51<?= preg_replace('/\D/','',$cel) ?>" target="_blank" style="color:var(--gris2);text-decoration:none;">
                  <i class="fab fa-whatsapp" style="color:#43a047;margin-right:3px;"></i><?= sanitize($cel) ?>
                </a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <?php
              $rolColores = [
                'admin'         => ['rgba(91,33,182,.3)',  '#c4b5fd'],
                'cliente_final' => ['rgba(237,232,42,.12)','var(--amarillo-texto)'],
                'tecnico'       => ['rgba(2,136,209,.2)',  '#4fc3f7'],
                'proyectista'   => ['rgba(255,152,0,.2)',  '#ffb74d'],
                'distribuidor'  => ['rgba(67,160,71,.2)',  '#81c784'],
                'motorizado'    => ['rgba(255,87,34,.2)',  '#ffb399'],
              ];
              $rc = $rolColores[$u['rol']] ?? ['rgba(255,255,255,.1)', 'var(--gris2)'];
              $etiquetasRol = ['admin'=>'ADMIN','cliente_final'=>'CLIENTE FINAL','tecnico'=>'TÉCNICO','proyectista'=>'PROYECTISTA','distribuidor'=>'DISTRIBUIDOR','motorizado'=>'MOTORIZADO','cliente'=>'CLIENTE'];
            ?>
            <td><span style="padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700;white-space:nowrap;background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;"><?= $etiquetasRol[$u['rol']] ?? strtoupper($u['rol']) ?></span></td>
            <td style="text-align:center;">
              <?php if ($u['rol'] === 'admin'): ?>
                <span style="color:var(--gris3);">—</span>
              <?php elseif ($tieneVerificadoCol): ?>
                <form method="POST" action="?tab=usuarios" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_verificado">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                  <button type="submit" title="<?= !empty($u['verificado']) ? 'Verificado — clic para retirar' : 'Marcar como verificado' ?>"
                    style="border:none;background:none;cursor:pointer;font-size:18px;color:<?= !empty($u['verificado']) ? '#43a047' : 'var(--gris3)' ?>;">
                    <i class="fas <?= !empty($u['verificado']) ? 'fa-check-circle' : 'fa-circle' ?>"></i>
                  </button>
                </form>
              <?php else: ?>
                <span style="color:var(--gris3);font-size:11px;" title="Corre migracion_roles.sql para activar esto">N/D</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center;font-weight:700;color:var(--amarillo-texto);"><?= $u['n_pedidos'] ?></td>
            <td><span style="padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700;background:<?= $u['activo']?'rgba(67,160,71,.2)':'rgba(229,57,53,.2)' ?>;color:<?= $u['activo']?'#81c784':'#ff6b6b' ?>;"><?= $u['activo']?'Activo':'Inactivo' ?></span></td>
            <td class="col-sticky-actions">
              <div style="display:flex;gap:5px;">
                <button class="btn-sm btn-edit" onclick='abrirModalUser(<?= htmlspecialchars(json_encode($u),ENT_QUOTES) ?>)'>
                  <i class="fas fa-edit"></i>
                </button>
                <?php if($u['id']!=(int)$_SESSION['usuario_id']): ?>
                <!-- Formulario oculto para eliminar usuario -->
                <form id="form-deluser-<?= $u['id'] ?>" method="POST" action="?tab=usuarios" class="form-del-hidden">
                  <input type="hidden" name="action" value="eliminar_usuario">
                  <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                </form>
                <button type="button" class="btn-sm btn-del"
                        onclick="eliminarUsuario(<?= $u['id'] ?>, '<?= htmlspecialchars(sanitize($u['nombre']),ENT_QUOTES) ?>')">
                  <i class="fas fa-trash"></i>
                </button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($usuariosList)): ?><tr><td colspan="10" style="text-align:center;padding:40px;color:var(--gris3);">No hay usuarios</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── PEDIDOS ─────────────────────────────────────────── -->
    <?php elseif ($tab === 'pedidos'): ?>

    <h2 style="font-size:20px;font-weight:900;color:var(--blanco);margin-bottom:20px;"><i class="fas fa-shopping-bag" style="color:var(--amarillo-texto);"></i> Pedidos Recientes</h2>
    <div class="dash-panel">
      <div style="overflow-x:auto;">
        <table class="atbl">
          <thead><tr><th>#</th><th>Cliente</th><th>Total</th><th>Entrega</th><th>Pago</th><th>Estado</th><th>Fecha</th><th>Acción</th></tr></thead>
          <tbody>
          <?php foreach($pedidosAdmin as $ped): ?>
          <?php
            $entregaTipo = $ped['tipo_entrega'] ?? ($ped['tipo_envio'] ?? 'recojo_tienda');
            $entregaTexto = match ($entregaTipo) {
              'delivery' => '🚚 Delivery',
              'provincia' => '🚚 Provincia',
              'recojo_tienda' => '🏪 Tienda',
              default => '🏪 Tienda'
            };
          ?>
          <tr>
            <td style="font-weight:800;color:var(--amarillo-texto);">#<?= str_pad($ped['id'],6,'0',STR_PAD_LEFT) ?></td>
            <td>
              <div style="font-weight:600;color:var(--blanco);"><?= sanitize($ped['nombre'].' '.$ped['apellido']) ?></div>
              <div style="font-size:11px;color:var(--gris3);"><?= sanitize($ped['email']) ?></div>
            </td>
            <td style="font-weight:800;color:var(--amarillo-texto);"><?= formatPrice($ped['total']) ?></td>
            <td style="font-size:12px;color:var(--gris3);"><?= $entregaTexto ?></td>
            <td style="font-size:12px;color:var(--gris3);text-transform:capitalize;"><?= sanitize($ped['metodo_pago']??'-') ?></td>
            <td><span class="eb eb-<?= $ped['estado'] ?>"><?= ucfirst($ped['estado']) ?></span></td>
            <td style="font-size:11px;color:var(--gris3);"><?= date('d/m/Y H:i',strtotime($ped['creado_en'])) ?></td>
            <td>
              <select onchange="cambiarEstado(<?= $ped['id'] ?>,this.value)"
                style="padding:4px 8px;background:var(--bg3);border:1px solid var(--borde);border-radius:6px;font-size:12px;color:var(--blanco);cursor:pointer;">
                <?php foreach(['pendiente','confirmado','procesando','enviado','entregado','cancelado'] as $est): ?>
                  <option value="<?= $est ?>" <?= $ped['estado']===$est?'selected':'' ?>><?= ucfirst($est) ?></option>
                <?php endforeach; ?>
              </select>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php endif; ?>
  </main>
</div>
</div>

<!-- MODAL PRODUCTO -->
<div class="modal-overlay" id="modal-prod">
  <div class="modal-box" style="max-width:680px;">
    <button class="modal-close" onclick="cerrar('modal-prod')">✕</button>
    <div class="modal-title"><i class="fas fa-box" style="color:var(--amarillo-texto);"></i> <span id="mprod-title">Nuevo Producto</span></div>
    <form method="POST" enctype="multipart/form-data" id="form-prod" action="?tab=productos">
      <input type="hidden" name="action" value="guardar_producto">
      <input type="hidden" name="prod_id" id="prod_id" value="0">
      <input type="hidden" name="imagen_actual"  id="imagen_actual"  value="">
      <input type="hidden" name="imagen2_actual" id="imagen2_actual" value="">
      <div class="form-row">
        <div class="form-group"><label>Nombre del producto *</label><input type="text" name="nombre" id="pnombre" required></div>
        <div class="form-group"><label>Marca</label><input type="text" name="marca" id="pmarca" placeholder="Hikvision"></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Categoría *</label>
          <select name="categoria_id" id="pcat" required>
            <option value="">Selecciona...</option>
<?php
$catsModal = $pdo->query("SELECT * FROM categorias ORDER BY COALESCE(padre_id, id), nombre")->fetchAll();
$catByIdM = [];
foreach ($catsModal as $c) $catByIdM[$c['id']] = $c + ['hijos' => []];
$raicesM = [];
foreach ($catByIdM as $id => &$c) {
    if (is_null($c['padre_id']))              $raicesM[] = &$c;
    elseif (isset($catByIdM[$c['padre_id']])) $catByIdM[$c['padre_id']]['hijos'][] = &$c;
}
unset($c);
function renderOptsModal($cats, $nivel = 0) {
    foreach ($cats as $c) {
        if ($nivel === 0)      $pre = '';
        elseif ($nivel === 1)  $pre = '&nbsp;&nbsp;&nbsp;↳ ';
        else                   $pre = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;· ';
        $style = $nivel === 0 ? 'font-weight:800;' : ($nivel === 1 ? 'font-weight:600;' : '');
        echo '<option value="'.$c['id'].'" style="'.$style.'">'.$pre.sanitize($c['nombre']).'</option>';
        if (!empty($c['hijos'])) renderOptsModal($c['hijos'], $nivel + 1);
}
}
renderOptsModal($raicesM);
?>
          </select>
        </div>
        <div class="form-group"><label>Modelo</label><input type="text" name="modelo" id="pmodelo"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Precio (S/) *</label><input type="number" name="precio" id="pprecio" step="0.01" min="0" required></div>
        <div class="form-group"><label>Precio oferta <span style="color:var(--gris3);font-size:11px;">(opcional)</span></label><input type="number" name="precio_oferta" id="pprecio_oferta" step="0.01" min="0" placeholder="Vacío = sin oferta"></div>
      </div>
      <div class="form-row">
          <div class="form-group"><label>Stock</label><input type="text" name="stock" id="pstock" placeholder="Ej: 20+ / 10-20 / Agotado" style="width:100%;"></div>        <div class="form-group">
          <label>Imagen</label>
          <div id="img-preview" style="margin-bottom:6px;"></div>
          <input type="file" name="imagen" id="pimagen" accept="image/*" style="padding:6px;border:1.5px solid var(--borde);border-radius:var(--r);width:100%;color:var(--blanco);background:var(--bg3);">
        </div>
        <div class="form-group">
          <label>Imagen hover <span style="color:var(--gris3);font-size:11px;">opcional</span></label>
          <div id="img2-preview" style="margin-bottom:6px;"></div>
          <input type="file" name="imagen2" id="pimagen2" accept="image/*" style="padding:6px;border:1.5px solid var(--borde);border-radius:var(--r);width:100%;color:var(--blanco);background:var(--bg3);">
        </div>
        <div class="form-group">
          <label>Potencia VA <span style="color:var(--gris3);font-size:11px;">solo para UPS</span></label>
          <input type="number" name="potencia_va" id="ppotencia_va" min="0" placeholder="Ej: 850" style="width:100%;">
        </div>
      </div>
      <div class="form-group"><label>Descripción</label><textarea name="descripcion" id="pdesc" rows="2"></textarea></div>
      <div class="form-group"><label>Especificaciones</label><textarea name="especificaciones" id="pespec" rows="2"></textarea></div>
      <div style="display:flex;gap:20px;align-items:flex-end;margin-bottom:18px;flex-wrap:wrap;">
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gris2);"><input type="checkbox" name="destacado" id="pdestacado"> Destacado</label>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gris2);"><input type="checkbox" name="activo" id="pactivo" checked> Activo en tienda</label>
        <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gris2);"><input type="checkbox" name="canjear" id="pcanjear"> Canjear</label>
        <div class="form-group" style="flex:1;max-width:220px;min-width:180px;">
          <label>Puntos para canjear</label>
          <input type="number" name="canje_puntos" id="pcanje_puntos" min="0" placeholder="Ej: 100" style="width:100%;" disabled>
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:12px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:14px;font-weight:800;cursor:pointer;"><i class="fas fa-save"></i> Guardar producto</button>
        <button type="button" onclick="cerrar('modal-prod')" style="padding:12px 20px;background:var(--bg3);color:var(--gris2);border:1px solid var(--borde);border-radius:var(--r);font-size:14px;font-weight:700;cursor:pointer;">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL USUARIO -->
<div class="modal-overlay" id="modal-user">
  <div class="modal-box">
    <button class="modal-close" onclick="cerrar('modal-user')">✕</button>
    <div class="modal-title"><i class="fas fa-user" style="color:var(--amarillo-texto);"></i> <span id="muser-title">Nuevo Usuario</span></div>
    <form method="POST" id="form-user" action="?tab=usuarios">
      <input type="hidden" name="action" value="guardar_usuario">
      <input type="hidden" name="user_id" id="user_id" value="0">
      <div class="form-group">
        <label>Tipo y Número de Documento *</label>
        <div style="display:flex;gap:8px;">
          <select name="tipo_documento" id="u_tipo" onchange="updDoc()" style="width:105px;padding:11px 10px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);font-size:14px;color:var(--blanco);">
            <option value="DNI">DNI</option>
            <option value="RUC">RUC</option>
          </select>
          <input type="text" name="dni_ruc" id="u_dni" placeholder="8 dígitos" maxlength="8" style="flex:1;">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Nombre *</label><input type="text" name="u_nombre" id="u_nombre" placeholder="Ej: Juan" required></div>
        <div class="form-group"><label>Apellido *</label><input type="text" name="u_apellido" id="u_apellido" placeholder="Ej: Pérez"></div>
      </div>
      <div class="form-group"><label>Correo Electrónico *</label><input type="email" name="u_email" id="u_email" placeholder="correo@ejemplo.com" required></div>
      <div class="form-row">
        <div class="form-group"><label>Celular *</label><input type="tel" name="u_celular" id="u_celular" placeholder="987 654 321"></div>
        <div class="form-group">
          <label>Fecha de Nacimiento <span style="color:var(--gris3);font-size:11px;">(opcional)</span></label>
          <input type="date" name="u_fnac" id="u_fnac" max="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group"><label>Dirección <span style="color:var(--gris3);font-size:11px;">(opcional)</span></label><input type="text" name="u_direccion" id="u_direccion" placeholder="Av. Ejemplo 123, Lima"></div>
      <div class="form-row">
        <div class="form-group"><label>Puntos</label><input type="number" name="u_puntos" id="u_puntos" min="0" value="0" placeholder="0"></div>
        <div class="form-group" id="u_pass_group">
          <label>Contraseña <span id="pass-hint" style="font-size:11px;color:var(--gris3);">(obligatorio)</span></label>
          <div class="password-wrap">
            <input type="password" name="u_pass" id="u_pass" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
            <button type="button" class="toggle-password" id="btn-toggle-pass"><i class="fas fa-eye"></i></button>
          </div>
        </div>
        <div class="form-group">
          <label>Rol *</label>
          <select name="u_rol" id="u_rol" style="width:100%;padding:11px 14px;background:var(--bg3);border:1.5px solid var(--borde);border-radius:var(--r);font-size:14px;color:var(--blanco);">
            <option value="cliente_final">Cliente Final</option>
            <option value="tecnico">Técnico</option>
            <option value="proyectista">Proyectista</option>
            <option value="distribuidor">Distribuidor</option>
            <option value="motorizado">Motorizado</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
      </div>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gris2);margin-bottom:10px;">
        <input type="checkbox" name="u_activo" id="u_activo" checked> Usuario activo
      </label>
      <?php if ($tieneVerificadoCol): ?>
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;color:var(--gris2);margin-bottom:18px;">
        <input type="checkbox" name="u_verificado" id="u_verificado"> <i class="fas fa-check-circle" style="color:#43a047;"></i> Categoría verificada (Técnico/Proyectista/Distribuidor confirmado)
      </label>
      <?php endif; ?>
      <div style="display:flex;gap:10px;">
        <button type="submit" style="flex:1;padding:12px;background:var(--amarillo);color:#000;border:none;border-radius:var(--r);font-size:14px;font-weight:800;cursor:pointer;"><i class="fas fa-save"></i> Guardar usuario</button>
        <button type="button" onclick="cerrar('modal-user')" style="padding:12px 20px;background:var(--bg3);color:var(--gris2);border:1px solid var(--borde);border-radius:var(--r);font-size:14px;font-weight:700;cursor:pointer;">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── FUNCIONES GLOBALES — fuera de cualquier wrapper ──────────
function togglePass(id, btn) {
  var input = document.getElementById(id);
  if (!input) {
    input = document.querySelector('input[name="u_pass"]');
  }
  if (!input) return;
  var icon = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    if (icon) { icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
  } else {
    input.type = 'password';
    if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
  }
}
function cerrar(id) { document.getElementById(id).classList.remove('open'); }
function confirmar(msg, cb) { cb(window.confirm(msg)); }
function updDoc() {
  var t = document.getElementById('u_tipo').value;
  var c = document.getElementById('u_dni');
  c.maxLength = t === 'DNI' ? 8 : 11;
  c.placeholder = t === 'DNI' ? '8 dígitos' : '11 dígitos';
}
function abrirModalUser(u) {
  var el = function(id) { return document.getElementById(id); };
  // Limpiar contraseña siempre — evita autofill del navegador
  el('u_pass').value = '';
  el('u_pass').setAttribute('type', 'password');
  var icon = document.querySelector('#btn-toggle-pass i');
  if (icon) { icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }

  if (u) {
    el('muser-title').textContent = 'Editar Usuario';
    el('user_id').value = u.id;
    el('u_tipo').value = u.tipo_documento || 'DNI'; updDoc();
    el('u_dni').value = u.dni_ruc || '';
    el('u_nombre').value = u.nombre || '';
    el('u_apellido').value = u.apellido || '';
    el('u_email').value = u.email || '';
    el('u_celular').value = u.celular || u.telefono || '';
    el('u_fnac').value = u.fecha_nacimiento || '';
    el('u_direccion').value = u.direccion_entrega || u.direccion || '';
    el('u_rol').value = u.rol || 'cliente_final';
    el('u_activo').checked = u.activo == 1;
    el('u_puntos').value = typeof u.puntos !== 'undefined' ? u.puntos : 0;
    if (el('u_verificado')) el('u_verificado').checked = u.verificado == 1;
    el('u_pass_group').style.display = 'none';
  } else {
    el('muser-title').textContent = 'Nuevo Usuario';
    document.getElementById('form-user').reset();
    el('user_id').value = 0;
    el('u_tipo').value = 'DNI'; updDoc();
    el('u_pass_group').style.display = 'block';
    el('pass-hint').textContent = '(obligatorio)';
  }
  document.getElementById('modal-user').classList.add('open');
}
function abrirModalProd(p) {
  var SITE_URL = '<?= SITE_URL ?>';
  var el = function(id) { return document.getElementById(id); };
  if (p) {
    el('mprod-title').textContent = 'Editar Producto';
    el('prod_id').value = p.id;
    el('pnombre').value = p.nombre || '';
    el('pcat').value = p.categoria_id || '';
    el('pmarca').value = p.marca || '';
    el('pmodelo').value = p.modelo || '';
    el('pstock').value = p.stock || '';
    el('pprecio').value = p.precio || '';
    el('pprecio_oferta').value = p.precio_oferta || '';
    el('pdesc').value = p.descripcion || '';
    el('pespec').value = p.especificaciones || '';
    el('pdestacado').checked = p.destacado == 1;
    el('pactivo').checked = p.activo == 1;
    el('imagen_actual').value = p.imagen || '';
    el('img-preview').innerHTML = p.imagen
      ? '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;"><img src="' + SITE_URL + '/' + p.imagen + '" style="width:70px;height:70px;object-fit:cover;border-radius:8px;"><button type="button" onclick="eliminarImagen(\'imagen_actual\',\'img-preview\',\'pimagen\')" style="padding:5px 10px;background:rgba(229,57,53,.15);color:#ff6b6b;border:1px solid rgba(229,57,53,.3);border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;"><i class="fas fa-trash"></i> Quitar</button></div>'
      : '';
    if (el('imagen2_actual')) {
      el('imagen2_actual').value = p.imagen2 || '';
      el('img2-preview').innerHTML = p.imagen2
        ? '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;"><img src="' + SITE_URL + '/' + p.imagen2 + '" style="width:70px;height:70px;object-fit:cover;border-radius:8px;"><button type="button" onclick="eliminarImagen(\'imagen2_actual\',\'img2-preview\',\'pimagen2\')" style="padding:5px 10px;background:rgba(229,57,53,.15);color:#ff6b6b;border:1px solid rgba(229,57,53,.3);border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;"><i class="fas fa-trash"></i> Quitar</button></div>'
        : '';
    }
    if (el('ppotencia_va')) el('ppotencia_va').value = p.potencia_va || '';
    var canjePts = parseInt(p.canje_puntos) || 0;
    el('pcanjear').checked = canjePts > 0;
    el('pcanje_puntos').disabled = !el('pcanjear').checked;
    el('pcanje_puntos').value = canjePts > 0 ? canjePts : '';
  } else {
    el('mprod-title').textContent = 'Nuevo Producto';
    document.getElementById('form-prod').reset();
    el('prod_id').value = 0;
    if (el('pcanjear')) el('pcanjear').checked = false;
    if (el('pcanje_puntos')) {
      el('pcanje_puntos').disabled = true;
      el('pcanje_puntos').value = '';
    }
    el('img-preview').innerHTML = '';
    if (el('img2-preview')) el('img2-preview').innerHTML = '';
  }
  document.getElementById('modal-prod').classList.add('open');
}
function eliminarProducto(id, nombre) {
  confirmar('Eliminar: ' + nombre + '\n\nEsta acción NO se puede deshacer.', function(ok) {
    if (ok) document.getElementById('form-delprod-' + id).submit();
  });
}
if (document.getElementById('pcanjear')) {
  document.getElementById('pcanjear').addEventListener('change', function() {
    var pts = document.getElementById('pcanje_puntos');
    if (!pts) return;
    pts.disabled = !this.checked;
    if (!this.checked) pts.value = '';
  });
}
function eliminarUsuario(id, nombre) {
  confirmar('¿Eliminar a ' + nombre + '?', function(ok) {
    if (ok) document.getElementById('form-deluser-' + id).submit();
  });
}
function eliminarImagen(campoActual, previewId, inputId) {
  confirmar('¿Quitar esta imagen?', function(ok) {
    if (!ok) return;
    document.getElementById(campoActual).value = '';
    document.getElementById(previewId).innerHTML = '<span style="font-size:11px;color:#ff6b6b;"><i class="fas fa-check-circle"></i> Imagen quitada.</span>';
    var inp = document.getElementById(inputId);
    if (inp) inp.value = '';
  });
}
function cambiarEstado(id, estado) {
  fetch('<?= SITE_URL ?>/admin/actualizar-pedido.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'accion=estado&pedido_id=' + id + '&estado=' + estado
  }).then(function(r) { return r.json(); }).then(function(d) {
    if (typeof showToast === 'function')
      showToast(d.success ? 'Estado actualizado: ' + estado : 'Error al actualizar', d.success);
  });
}

// ── Listeners que necesitan DOM listo ────────────────────────
document.addEventListener('DOMContentLoaded', function() {

  var dniInput = document.getElementById('u_dni');
  if (dniInput) dniInput.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '');
  });

  var pimagen = document.getElementById('pimagen');
  if (pimagen) pimagen.addEventListener('change', function() {
    var r = new FileReader();
    r.onload = function(e) {
      document.getElementById('img-preview').innerHTML =
        '<img src="' + e.target.result + '" style="width:70px;height:70px;object-fit:cover;border-radius:8px;">';
    };
    r.readAsDataURL(this.files[0]);
  });
});
document.addEventListener('click', function(e) {
  var btn = e.target.closest('#btn-toggle-pass');
  if (btn) {
    e.preventDefault();
    e.stopPropagation();
    togglePass('u_pass', btn);
  }
});
document.addEventListener('click', function(e) {
  var btn = e.target.closest('#btn-toggle-pass');
  if (btn) {
    e.preventDefault();
    e.stopPropagation();
    togglePass('u_pass', btn);
  }
});
</script>
<?php include '../includes/footer.php'; ?>