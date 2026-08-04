<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Imágenes Cámaras WiFi (Home)';
$pdo = getDB();
$msg = '';

// Crear tabla si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `camwifi_imagenes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `nombre` varchar(150) DEFAULT NULL,
        `marca` varchar(80) DEFAULT NULL,
        `imagen` varchar(300) NOT NULL,
        `orden` int(11) DEFAULT 0,
        `activo` tinyint(1) DEFAULT 1,
        `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// Acciones
$action = $_GET['action'] ?? '';
if ($action === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE camwifi_imagenes SET activo = NOT activo WHERE id=?")->execute([(int)$_GET['id']]);
    header('Location: camwifi.php?msg=updated'); exit;
}
if ($action === 'delete' && isset($_GET['id'])) {
    $cid = (int)$_GET['id'];
    $b = $pdo->prepare("SELECT imagen FROM camwifi_imagenes WHERE id=?");
    $b->execute([$cid]);
    $old = $b->fetchColumn();
    if ($old && file_exists(dirname(__DIR__).'/'.$old)) unlink(dirname(__DIR__).'/'.$old);
    $pdo->prepare("DELETE FROM camwifi_imagenes WHERE id=?")->execute([$cid]);
    header('Location: camwifi.php?msg=deleted'); exit;
}

// Guardar imagen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['id'] ?? 0);
    $nombre = sanitize(trim($_POST['nombre'] ?? ''));
    $marca  = sanitize(trim($_POST['marca']  ?? ''));
    $orden  = (int)($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen = sanitize($_POST['imagen_actual'] ?? '');

    if (!empty($_FILES['imagen']['name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $dir = dirname(__DIR__) . '/assets/img/camwifi/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nuevo = 'camwifi_'.time().'_'.rand(100,999).'.'.$ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dir.$nuevo)) {
                if ($imagen && file_exists(dirname(__DIR__).'/'.$imagen)) unlink(dirname(__DIR__).'/'.$imagen);
                $imagen = 'assets/img/camwifi/'.$nuevo;
            }
        }
    } elseif ($imagen === '' && !empty($_POST['imagen_anterior']) && file_exists(dirname(__DIR__).'/'.$_POST['imagen_anterior'])) {
        unlink(dirname(__DIR__).'/'.$_POST['imagen_anterior']);
    }

    if ($imagen) {
        if ($cid > 0) {
            $pdo->prepare("UPDATE camwifi_imagenes SET nombre=?,marca=?,imagen=?,orden=?,activo=? WHERE id=?")
                ->execute([$nombre,$marca,$imagen,$orden,$activo,$cid]);
            $msg = 'Imagen actualizada correctamente.';
        } else {
            $pdo->prepare("INSERT INTO camwifi_imagenes (nombre,marca,imagen,orden,activo) VALUES (?,?,?,?,?)")
                ->execute([$nombre,$marca,$imagen,$orden,$activo]);
            $msg = 'Imagen creada correctamente.';
        }
    } else {
        $msg = 'Debes subir una imagen.';
    }
}

$stmt = $pdo->query("SELECT * FROM camwifi ORDER BY id DESC");
$editItem = null;
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM camwifi_imagenes WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $editItem = $ep->fetch();
}
if (isset($_GET['msg'])) {
    $msgs = ['updated'=>'Estado actualizado.','deleted'=>'Imagen eliminada.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}
include '../includes/header.php';
?>

<style>
.cw-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 9000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.cw-modal-box {
    background: #ffffff;
    border: 1.5px solid #e0e0e4;
    border-radius: 16px;
    padding: 28px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}
.cw-modal-title {
    font-size: 18px;
    font-weight: 800;
    color: #111;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}
.cw-modal-title i { color: var(--primary, #CEFF04); }
.cw-modal-close {
    position: absolute;
    top: 16px;
    right: 16px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    line-height: 1;
}
.cw-modal-close:hover { color: #111; }
.cw-field { margin-bottom: 14px; }
.cw-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}
.cw-field input[type="text"],
.cw-field input[type="number"],
.cw-field input[type="file"] {
    width: 100%;
    padding: 9px 12px;
    background: #f5f5f7;
    border: 1.5px solid #e0e0e4;
    border-radius: 8px;
    color: #111;
    font-size: 14px;
    outline: none;
    transition: border-color .2s;
}
.cw-field input:focus { border-color: #b8e800; }
.cw-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cw-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    margin-bottom: 20px;
}
.cw-check input { width: auto; cursor: pointer; }
.cw-btn-save {
    flex: 1;
    padding: 11px;
    background: #CEFF04;
    color: #000;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    transition: background .2s;
}
.cw-btn-save:hover { background: #b8e800; }
.cw-btn-cancel {
    padding: 11px 20px;
    background: #f5f5f7;
    color: #555;
    border: 1.5px solid #e0e0e4;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.cw-btn-cancel:hover { background: #e8e8ec; }
.cw-preview-img {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    margin-bottom: 8px;
    display: block;
    border: 1px solid #e0e0e4;
}
.cw-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
    border: 1px solid #e0e0e4;
    background: #f5f5f7;
}
</style>

<div class="container" style="padding:24px 20px 60px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-camera" style="color:var(--primary);"></i> Imágenes Cámaras WiFi (Home)</h1>
            <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Cámaras WiFi Home</div>
            <div style="font-size:12px;color:var(--gray);margin-top:6px;max-width:560px;">
                Estas imágenes son las que se muestran en la sección "Cámaras WiFi" del inicio.
                Todas, al hacer clic, llevan al usuario a la categoría WiFi del catálogo — no necesitas tocar ningún link, solo subir fotos llamativas.
            </div>
        </div>
        <button onclick="document.getElementById('modal-cw').style.display='flex'"
                style="padding:10px 20px;background:var(--primary);color:#000;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-plus"></i> Nueva Imagen
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div>
    <?php endif; ?>

    <!-- LISTA -->
    <?php if (empty($items)): ?>
        <div style="text-align:center;padding:60px;background:white;border-radius:14px;border:1.5px dashed #e0e0e4;">
            <i class="fas fa-camera" style="font-size:48px;color:#ccc;margin-bottom:16px;display:block;"></i>
            <p style="color:#888;">No hay imágenes creadas aún. Mientras no agregues ninguna, el inicio mostrará el bloque ilustrativo por defecto.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach($items as $it): ?>
            <div style="background:white;border:1.5px solid #e0e0e4;border-radius:12px;padding:14px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.06);">
                <img src="<?= SITE_URL ?>/<?= sanitize($it['imagen']) ?>" class="cw-thumb">
                <div style="flex:1;min-width:160px;">
                    <div style="font-size:15px;font-weight:700;color:#111;"><?= sanitize($it['nombre'] ?: 'Sin nombre') ?></div>
                    <?php if($it['marca']): ?>
                        <div style="font-size:12px;color:#888;margin-top:2px;"><?= sanitize($it['marca']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="font-size:12px;color:#888;">Orden: <strong style="color:#111;"><?= (int)$it['orden'] ?></strong></div>
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $it['activo']?'#dcfce7':'#fee2e2' ?>;color:<?= $it['activo']?'#166534':'#991b1b' ?>;">
                    <?= $it['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
                <div style="display:flex;gap:6px;">
                    <a href="camwifi.php?edit=<?= $it['id'] ?>"
                       style="padding:7px 12px;background:#f0fdf4;color:#166534;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #bbf7d0;">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="camwifi.php?action=toggle&id=<?= $it['id'] ?>"
                       style="padding:7px 12px;background:#f5f5f7;color:#555;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #e0e0e4;">
                        <i class="fas fa-eye<?= $it['activo']?'-slash':'' ?>"></i>
                    </a>
                    <a href="javascript:void(0)"
                       onclick="eliminarCamwifi(<?= $it['id'] ?>, 'camwifi.php?action=delete&id=<?= $it['id'] ?>')"
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
<div id="modal-cw" class="cw-modal-overlay" style="display:<?= $editItem?'flex':'none' ?>;">
    <div class="cw-modal-box">
        <button class="cw-modal-close" onclick="document.getElementById('modal-cw').style.display='none'">×</button>
        <div class="cw-modal-title">
            <i class="fas fa-camera"></i>
            <?= $editItem ? 'Editar Imagen' : 'Nueva Imagen' ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $editItem['id'] ?? 0 ?>">
            <input type="hidden" name="imagen_actual" id="cw_imagen_actual" value="<?= sanitize($editItem['imagen'] ?? '') ?>">
            <input type="hidden" name="imagen_anterior" value="<?= sanitize($editItem['imagen'] ?? '') ?>">

            <div class="cw-field">
                <label>Imagen *</label>
                <div id="cw-img-preview">
                <?php if(!empty($editItem['imagen'])): ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <img src="<?= SITE_URL ?>/<?= sanitize($editItem['imagen']) ?>" class="cw-preview-img" style="margin-bottom:0;">
                        <button type="button" onclick="eliminarImagenCamwifi()"
                                style="padding:6px 12px;background:rgba(229,57,53,.1);color:#dc2626;border:1px solid rgba(229,57,53,.3);border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-trash"></i> Quitar
                        </button>
                    </div>
                <?php endif; ?>
                </div>
                <input type="file" name="imagen" id="cw_imagen_input" accept="image/*" <?= $editItem ? '' : 'required' ?>>
            </div>
            <div class="cw-field">
                <label>Nombre (opcional, se ve al pasar el mouse)</label>
                <input type="text" name="nombre" value="<?= sanitize($editItem['nombre'] ?? '') ?>"
                       placeholder="Ej: Cámara WiFi 360°">
            </div>

            <div class="cw-grid-2">
                <div class="cw-field">
                    <label>Marca (opcional)</label>
                    <input type="text" name="marca" value="<?= sanitize($editItem['marca'] ?? '') ?>"
                           placeholder="Ej: Dahua">
                </div>
                <div class="cw-field">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?= (int)($editItem['orden'] ?? 0) ?>" min="0">
                </div>
            </div>

            <label class="cw-check">
                <input type="checkbox" name="activo" <?= ($editItem['activo'] ?? 1) ? 'checked' : '' ?>>
                Activo (visible en el inicio)
            </label>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="cw-btn-save">
                    <i class="fas fa-save"></i> Guardar
                </button>
                <button type="button" class="cw-btn-cancel"
                        onclick="document.getElementById('modal-cw').style.display='none'">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function eliminarCamwifi(id, url) {
    confirmar('¿Eliminar esta imagen?', function(ok) {
        if (ok) window.location.href = url;
    });
}
function eliminarImagenCamwifi() {
    confirmar('¿Quitar esta imagen? Tendrás que subir una nueva para guardar.', function(ok) {
        if (!ok) return;
        document.getElementById('cw_imagen_actual').value = '';
        document.getElementById('cw-img-preview').innerHTML = '<span style="font-size:12px;color:#dc2626;"><i class="fas fa-check-circle"></i> Imagen quitada. Sube una nueva.</span>';
        var inp = document.getElementById('cw_imagen_input');
        inp.value = '';
        inp.required = true;
    });
}
</script>

<?php include '../includes/footer.php'; ?>