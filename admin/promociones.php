<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Promociones del Mes';
$pdo = getDB();
$msg = '';

// Obtener (o crear) la categoría interna de promociones
$catPromo = $pdo->query("SELECT id FROM categorias WHERE nombre='__PROMOCIONES__' LIMIT 1")->fetch();
if (!$catPromo) {
    $pdo->exec("INSERT INTO categorias (nombre, descripcion, icono, padre_id) VALUES ('__PROMOCIONES__','Categoría interna, no usar en menús','fa-fire',NULL)");
    $CAT_PROMO_ID = (int)$pdo->lastInsertId();
} else {
    $CAT_PROMO_ID = (int)$catPromo['id'];
}

// Acciones
$action = $_GET['action'] ?? '';
if ($action === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE productos SET activo = NOT activo WHERE id=? AND es_promo=1")->execute([(int)$_GET['id']]);
    header('Location: promociones.php?msg=updated'); exit;
}
if ($action === 'delete' && isset($_GET['id'])) {
    $pid = (int)$_GET['id'];
    $b = $pdo->prepare("SELECT imagen FROM productos WHERE id=? AND es_promo=1");
    $b->execute([$pid]);
    $old = $b->fetchColumn();
    if ($old && $old !== 'default.jpg' && file_exists(dirname(__DIR__).'/'.$old)) unlink(dirname(__DIR__).'/'.$old);
    $pdo->prepare("DELETE FROM productos WHERE id=? AND es_promo=1")->execute([$pid]);
    header('Location: promociones.php?msg=deleted'); exit;
}

// Guardar promo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid     = (int)($_POST['id'] ?? 0);
    $nombre  = sanitize(trim($_POST['nombre'] ?? ''));
    $marca   = sanitize(trim($_POST['marca'] ?? '')) ?: 'Promoción';
    $precio  = (float)($_POST['precio'] ?? 0);
    $stock   = max(0, (int)($_POST['stock'] ?? 0));
    $vence   = !empty($_POST['promo_vence']) ? $_POST['promo_vence'] : null;
    $activo  = isset($_POST['activo']) ? 1 : 0;
    $imagen  = sanitize($_POST['imagen_actual'] ?? 'default.jpg');

    if (!empty($_FILES['imagen']['name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $dir = dirname(__DIR__) . '/assets/img/promociones/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nuevo = 'promo_'.time().'_'.rand(100,999).'.'.$ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dir.$nuevo)) {
                if ($imagen && $imagen !== 'default.jpg' && file_exists(dirname(__DIR__).'/'.$imagen)) unlink(dirname(__DIR__).'/'.$imagen);
                $imagen = 'assets/img/promociones/'.$nuevo;
            }
        }
    } elseif ($imagen === '' && !empty($_POST['imagen_anterior']) && $_POST['imagen_anterior'] !== 'default.jpg' && file_exists(dirname(__DIR__).'/'.$_POST['imagen_anterior'])) {
        unlink(dirname(__DIR__).'/'.$_POST['imagen_anterior']);
        $imagen = 'default.jpg';
    }
    if (!$imagen) $imagen = 'default.jpg';

    if ($nombre && $precio > 0) {
        if ($pid > 0) {
            $pdo->prepare("UPDATE productos SET nombre=?,marca=?,precio=?,stock=?,imagen=?,promo_vence=?,activo=? WHERE id=? AND es_promo=1")
                ->execute([$nombre,$marca,$precio,$stock,$imagen,$vence,$activo,$pid]);
            $msg = 'Promoción actualizada correctamente.';
        } else {
            $pdo->prepare("INSERT INTO productos (categoria_id,nombre,marca,precio,stock,imagen,es_promo,promo_vence,activo,destacado)
                            VALUES (?,?,?,?,?,?,1,?,?,0)")
                ->execute([$CAT_PROMO_ID,$nombre,$marca,$precio,$stock,$imagen,$vence,$activo]);
            $msg = 'Promoción creada correctamente.';
        }
    } else {
        $msg = 'Nombre y precio son obligatorios.';
    }
}

$items = $pdo->query("SELECT * FROM productos WHERE es_promo=1 ORDER BY creado_en DESC")->fetchAll();
$editItem = null;
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM productos WHERE id=? AND es_promo=1");
    $ep->execute([(int)$_GET['edit']]);
    $editItem = $ep->fetch();
}
if (isset($_GET['msg'])) {
    $msgs = ['updated'=>'Estado actualizado.','deleted'=>'Promoción eliminada.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}
include '../includes/header.php';
?>

<style>
.pr-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9000; align-items:center; justify-content:center; padding:20px; }
.pr-modal-box { background:#fff; border:1.5px solid #e0e0e4; border-radius:16px; padding:28px; width:100%; max-width:540px; max-height:90vh; overflow-y:auto; position:relative; }
.pr-modal-title { font-size:18px; font-weight:800; color:#111; display:flex; align-items:center; gap:8px; margin-bottom:20px; }
.pr-modal-title i { color:var(--primary,#CEFF04); }
.pr-modal-close { position:absolute; top:16px; right:16px; background:none; border:none; font-size:24px; cursor:pointer; color:#666; line-height:1; }
.pr-modal-close:hover { color:#111; }
.pr-field { margin-bottom:14px; }
.pr-field label { display:block; font-size:12px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:.5px; margin-bottom:5px; }
.pr-field input[type="text"], .pr-field input[type="number"], .pr-field input[type="file"], .pr-field input[type="datetime-local"] {
  width:100%; padding:9px 12px; background:#f5f5f7; border:1.5px solid #e0e0e4; border-radius:8px; color:#111; font-size:14px; outline:none; transition:border-color .2s;
}
.pr-field input:focus { border-color:#b8e800; }
.pr-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.pr-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
.pr-check { display:flex; align-items:center; gap:8px; font-size:14px; color:#333; cursor:pointer; margin-bottom:20px; }
.pr-check input { width:auto; cursor:pointer; }
.pr-btn-save { flex:1; padding:11px; background:#CEFF04; color:#000; border:none; border-radius:8px; font-size:14px; font-weight:800; cursor:pointer; transition:background .2s; }
.pr-btn-save:hover { background:#b8e800; }
.pr-btn-cancel { padding:11px 20px; background:#f5f5f7; color:#555; border:1.5px solid #e0e0e4; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; }
.pr-btn-cancel:hover { background:#e8e8ec; }
.pr-preview-img { width:70px; height:70px; object-fit:cover; border-radius:8px; margin-bottom:8px; display:block; border:1px solid #e0e0e4; }
.pr-thumb { width:60px; height:60px; object-fit:cover; border-radius:8px; flex-shrink:0; border:1px solid #e0e0e4; background:#f5f5f7; }
</style>

<div class="container" style="padding:24px 20px 60px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-fire" style="color:var(--primary);"></i> Promociones del Mes</h1>
            <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Promociones</div>
            <div style="font-size:12px;color:var(--gray);margin-top:6px;max-width:560px;">
                Promociones independientes del catálogo: tú defines nombre, foto, precio y stock. Se muestran en la sección "Ofertas del Mes" del inicio y se agregan al carrito como cualquier producto.
            </div>
        </div>
        <button onclick="document.getElementById('modal-pr').style.display='flex'"
                style="padding:10px 20px;background:var(--primary);color:#000;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-plus"></i> Nueva Promoción
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <div style="text-align:center;padding:60px;background:white;border-radius:14px;border:1.5px dashed #e0e0e4;">
            <i class="fas fa-fire" style="font-size:48px;color:#ccc;margin-bottom:16px;display:block;"></i>
            <p style="color:#888;">No hay promociones creadas aún. Mientras no agregues ninguna, el inicio mostrará las ofertas normales del catálogo.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach($items as $it): ?>
            <div style="background:white;border:1.5px solid #e0e0e4;border-radius:12px;padding:14px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.06);">
                <img src="<?= SITE_URL ?>/<?= sanitize($it['imagen']) ?>" class="pr-thumb">
                <div style="flex:1;min-width:160px;">
                    <div style="font-size:15px;font-weight:700;color:#111;"><?= sanitize($it['nombre']) ?></div>
                    <div style="font-size:13px;color:#888;margin-top:2px;">
                        <?= formatPrice($it['precio']) ?> · Stock: <?= (int)$it['stock'] ?>
                        <?php if($it['promo_vence']): ?>
                            · Vence: <?= date('d/m/Y H:i', strtotime($it['promo_vence'])) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $it['activo']?'#dcfce7':'#fee2e2' ?>;color:<?= $it['activo']?'#166534':'#991b1b' ?>;">
                    <?= $it['activo'] ? 'Activa' : 'Inactiva' ?>
                </span>
                <div style="display:flex;gap:6px;">
                    <a href="promociones.php?edit=<?= $it['id'] ?>"
                       style="padding:7px 12px;background:#f0fdf4;color:#166534;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #bbf7d0;">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="promociones.php?action=toggle&id=<?= $it['id'] ?>"
                       style="padding:7px 12px;background:#f5f5f7;color:#555;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #e0e0e4;">
                        <i class="fas fa-eye<?= $it['activo']?'-slash':'' ?>"></i>
                    </a>
                    <a href="javascript:void(0)"
                       onclick="eliminarPromo(<?= $it['id'] ?>, 'promociones.php?action=delete&id=<?= $it['id'] ?>')"
                       style="padding:7px 12px;background:#fff1f1;color:#dc2626;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #fecaca;">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL CREAR/EDITAR -->
<div id="modal-pr" class="pr-modal-overlay" style="display:<?= $editItem?'flex':'none' ?>;">
    <div class="pr-modal-box">
        <button class="pr-modal-close" onclick="document.getElementById('modal-pr').style.display='none'">×</button>
        <div class="pr-modal-title">
            <i class="fas fa-fire"></i>
            <?= $editItem ? 'Editar Promoción' : 'Nueva Promoción' ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $editItem['id'] ?? 0 ?>">
            <input type="hidden" name="imagen_actual" id="pr_imagen_actual" value="<?= sanitize($editItem['imagen'] ?? 'default.jpg') ?>">
            <input type="hidden" name="imagen_anterior" value="<?= sanitize($editItem['imagen'] ?? '') ?>">

            <div class="pr-field">
                <label>Nombre de la promoción *</label>
                <input type="text" name="nombre" value="<?= sanitize($editItem['nombre'] ?? '') ?>"
                       placeholder="Ej: Combo Cámara + Instalación Gratis" required>
            </div>

            <div class="pr-field">
                <label>Imagen</label>
                <div id="pr-img-preview">
                <?php if(!empty($editItem['imagen']) && $editItem['imagen'] !== 'default.jpg'): ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <img src="<?= SITE_URL ?>/<?= sanitize($editItem['imagen']) ?>" class="pr-preview-img" style="margin-bottom:0;">
                        <button type="button" onclick="eliminarImagenPromo()"
                                style="padding:6px 12px;background:rgba(229,57,53,.1);color:#dc2626;border:1px solid rgba(229,57,53,.3);border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-trash"></i> Quitar
                        </button>
                    </div>
                <?php endif; ?>
                </div>
                <input type="file" name="imagen" id="pr_imagen_input" accept="image/*">
            </div>

            <div class="pr-grid-3">
                <div class="pr-field">
                    <label>Marca / Etiqueta</label>
                    <input type="text" name="marca" value="<?= sanitize($editItem['marca'] ?? '') ?>" placeholder="Ej: Promo">
                </div>
                <div class="pr-field">
                    <label>Precio (S/) *</label>
                    <input type="number" name="precio" value="<?= $editItem['precio'] ?? '' ?>" step="0.01" min="0.01" required>
                </div>
                <div class="pr-field">
                    <label>Stock disponible</label>
                    <input type="number" name="stock" value="<?= $editItem['stock'] ?? 10 ?>" min="0">
                </div>
            </div>

            <div class="pr-field">
                <label>Vence el (opcional)</label>
                <input type="datetime-local" name="promo_vence"
                       value="<?= !empty($editItem['promo_vence']) ? date('Y-m-d\TH:i', strtotime($editItem['promo_vence'])) : '' ?>">
            </div>

            <label class="pr-check">
                <input type="checkbox" name="activo" <?= ($editItem['activo'] ?? 1) ? 'checked' : '' ?>>
                Activa (visible en el inicio)
            </label>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="pr-btn-save">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <button type="button" class="pr-btn-cancel"
                        onclick="document.getElementById('modal-pr').style.display='none'">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function eliminarPromo(id, url) {
    confirmar('¿Eliminar esta promoción?', function(ok) {
        if (ok) window.location.href = url;
    });
}
function eliminarImagenPromo() {
    confirmar('¿Quitar esta imagen?', function(ok) {
        if (!ok) return;
        document.getElementById('pr_imagen_actual').value = '';
        document.getElementById('pr-img-preview').innerHTML = '<span style="font-size:12px;color:#dc2626;"><i class="fas fa-check-circle"></i> Imagen quitada.</span>';
        var inp = document.getElementById('pr_imagen_input');
        if (inp) inp.value = '';
    });
}
</script>

<?php include '../includes/footer.php'; ?>