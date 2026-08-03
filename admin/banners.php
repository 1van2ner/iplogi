<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Banners Promocionales';
$pdo = getDB();
$msg = '';

// Crear tabla si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `banners` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `titulo` varchar(150) NOT NULL,
        `subtitulo` varchar(250) DEFAULT NULL,
        `texto_boton` varchar(80) DEFAULT 'Ver más',
        `url_boton` varchar(300) DEFAULT NULL,
        `imagen` varchar(300) DEFAULT NULL,
        `color_fondo` varchar(20) DEFAULT '#1a1a2e',
        `color_texto` varchar(20) DEFAULT '#ffffff',
        `orden` int(11) DEFAULT 0,
        `activo` tinyint(1) DEFAULT 1,
        `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

// Acciones
$action = $_GET['action'] ?? '';
if ($action === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE banners SET activo = NOT activo WHERE id=?")->execute([(int)$_GET['id']]);
    header('Location: banners.php?msg=updated'); exit;
}
if ($action === 'delete' && isset($_GET['id'])) {
    $bid = (int)$_GET['id'];
    $b = $pdo->prepare("SELECT imagen FROM banners WHERE id=?");
    $b->execute([$bid]);
    $old = $b->fetchColumn();
    if ($old && file_exists(dirname(__DIR__).'/'.$old)) unlink(dirname(__DIR__).'/'.$old);
    $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$bid]);
    header('Location: banners.php?msg=deleted'); exit;
}

// Guardar banner
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bid      = (int)($_POST['id'] ?? 0);
    $titulo   = sanitize(trim($_POST['titulo']       ?? ''));
    $sub      = sanitize(trim($_POST['subtitulo']    ?? ''));
    $txtbtn   = sanitize(trim($_POST['texto_boton']  ?? 'Ver más'));
    $urlbtn   = sanitize(trim($_POST['url_boton']    ?? ''));
    $colorf   = sanitize(trim($_POST['color_fondo']  ?? '#1a1a2e'));
    $colort   = sanitize(trim($_POST['color_texto']  ?? '#ffffff'));
    $orden    = (int)($_POST['orden']   ?? 0);
    $activo   = isset($_POST['activo']) ? 1 : 0;
    $imagen   = sanitize($_POST['imagen_actual'] ?? '');

    if (!empty($_FILES['imagen']['name'])) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            $dir = dirname(__DIR__) . '/assets/img/banners/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nuevo = 'banner_'.time().'_'.rand(100,999).'.'.$ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $dir.$nuevo)) {
                if ($imagen && file_exists(dirname(__DIR__).'/'.$imagen)) unlink(dirname(__DIR__).'/'.$imagen);
                $imagen = 'assets/img/banners/'.$nuevo;
            }
        }
    } elseif ($imagen === '' && !empty($_POST['imagen_anterior']) && file_exists(dirname(__DIR__).'/'.$_POST['imagen_anterior'])) {
        // El usuario quitó la imagen sin subir otra: borrar el archivo físico viejo
        unlink(dirname(__DIR__).'/'.$_POST['imagen_anterior']);
    }

    if ($titulo) {
        if ($bid > 0) {
            $pdo->prepare("UPDATE banners SET titulo=?,subtitulo=?,texto_boton=?,url_boton=?,imagen=?,color_fondo=?,color_texto=?,orden=?,activo=? WHERE id=?")
                ->execute([$titulo,$sub,$txtbtn,$urlbtn,$imagen,$colorf,$colort,$orden,$activo,$bid]);
            $msg = 'Banner actualizado correctamente.';
        } else {
            $pdo->prepare("INSERT INTO banners (titulo,subtitulo,texto_boton,url_boton,imagen,color_fondo,color_texto,orden,activo) VALUES (?,?,?,?,?,?,?,?,?)")
                ->execute([$titulo,$sub,$txtbtn,$urlbtn,$imagen,$colorf,$colort,$orden,$activo]);
            $msg = 'Banner creado correctamente.';
        }
    }
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY orden ASC, id DESC")->fetchAll();
$editBanner = null;
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM banners WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $editBanner = $ep->fetch();
}
if (isset($_GET['msg'])) {
    $msgs = ['updated'=>'Estado actualizado.','deleted'=>'Banner eliminado.'];
    $msg = $msgs[$_GET['msg']] ?? '';
}
include '../includes/header.php';
?>

<style>
.ban-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 9000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.ban-modal-box {
    background: #ffffff;
    border: 1.5px solid #e0e0e4;
    border-radius: 16px;
    padding: 28px;
    width: 100%;
    max-width: 560px;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
}
.ban-modal-title {
    font-size: 18px;
    font-weight: 800;
    color: #111;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 20px;
}
.ban-modal-title i { color: var(--primary, #CEFF04); }
.ban-modal-close {
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
.ban-modal-close:hover { color: #111; }
.ban-field { margin-bottom: 14px; }
.ban-field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #555;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 5px;
}
.ban-field input[type="text"],
.ban-field input[type="number"],
.ban-field input[type="file"] {
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
.ban-field input:focus { border-color: #b8e800; }
.ban-field input[type="color"] {
    width: 100%;
    height: 42px;
    padding: 2px 4px;
    background: #f5f5f7;
    border: 1.5px solid #e0e0e4;
    border-radius: 8px;
    cursor: pointer;
}
.ban-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.ban-check {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #333;
    cursor: pointer;
    margin-bottom: 20px;
}
.ban-check input { width: auto; cursor: pointer; }
.ban-btn-save {
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
.ban-btn-save:hover { background: #b8e800; }
.ban-btn-cancel {
    padding: 11px 20px;
    background: #f5f5f7;
    color: #555;
    border: 1.5px solid #e0e0e4;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
}
.ban-btn-cancel:hover { background: #e8e8ec; }
.ban-preview-img {
    height: 50px;
    object-fit: contain;
    border-radius: 6px;
    margin-bottom: 8px;
    display: block;
    border: 1px solid #e0e0e4;
}
</style>

<div class="container" style="padding:24px 20px 60px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-images" style="color:var(--primary);"></i> Banners Promocionales</h1>
            <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Banners</div>
        </div>
        <button onclick="document.getElementById('modal-banner').style.display='flex'"
                style="padding:10px 20px;background:var(--primary);color:#000;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-plus"></i> Nuevo Banner
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?= sanitize($msg) ?></div>
    <?php endif; ?>

    <!-- LISTA DE BANNERS -->
    <?php if (empty($banners)): ?>
        <div style="text-align:center;padding:60px;background:white;border-radius:14px;border:1.5px dashed #e0e0e4;">
            <i class="fas fa-images" style="font-size:48px;color:#ccc;margin-bottom:16px;display:block;"></i>
            <p style="color:#888;">No hay banners creados aún.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <?php foreach($banners as $b): ?>
            <div style="background:white;border:1.5px solid #e0e0e4;border-radius:12px;padding:16px 20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.06);">
                <!-- Preview color -->
                <div style="width:60px;height:44px;border-radius:8px;background:<?= sanitize($b['color_fondo']) ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:<?= sanitize($b['color_texto']) ?>;text-align:center;padding:4px;border:1px solid #e0e0e4;">
                    <?= $b['imagen'] ? '<i class="fas fa-image" style="font-size:18px;"></i>' : 'PREV' ?>
                </div>
                <!-- Info -->
                <div style="flex:1;min-width:160px;">
                    <div style="font-size:15px;font-weight:700;color:#111;"><?= sanitize($b['titulo']) ?></div>
                    <?php if($b['subtitulo']): ?>
                        <div style="font-size:12px;color:#888;margin-top:2px;"><?= sanitize(substr($b['subtitulo'],0,60)) ?>...</div>
                    <?php endif; ?>
                </div>
                <!-- Orden -->
                <div style="font-size:12px;color:#888;">Orden: <strong style="color:#111;"><?= (int)$b['orden'] ?></strong></div>
                <!-- Estado -->
                <span style="padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;background:<?= $b['activo']?'#dcfce7':'#fee2e2' ?>;color:<?= $b['activo']?'#166534':'#991b1b' ?>;">
                    <?= $b['activo'] ? 'Activo' : 'Inactivo' ?>
                </span>
                <!-- Acciones -->
                <div style="display:flex;gap:6px;">
                    <a href="banners.php?edit=<?= $b['id'] ?>"
                       style="padding:7px 12px;background:#f0fdf4;color:#166534;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #bbf7d0;">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="banners.php?action=toggle&id=<?= $b['id'] ?>"
                       style="padding:7px 12px;background:#f5f5f7;color:#555;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #e0e0e4;">
                        <i class="fas fa-eye<?= $b['activo']?'-slash':'' ?>"></i>
                    </a>
                    <a href="javascript:void(0)"
                       onclick="eliminarBanner(<?= $b['id'] ?>, 'banners.php?action=delete&id=<?= $b['id'] ?>')"
                       style="padding:7px 12px;background:#fff1f1;color:#dc2626;border-radius:6px;font-size:12px;font-weight:700;text-decoration:none;border:1px solid #fecaca;">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL CREAR/EDITAR BANNER -->
<div id="modal-banner" class="ban-modal-overlay" style="display:<?= $editBanner?'flex':'none' ?>;">
    <div class="ban-modal-box">
        <button class="ban-modal-close" onclick="document.getElementById('modal-banner').style.display='none'">×</button>
        <div class="ban-modal-title">
            <i class="fas fa-image"></i>
            <?= $editBanner ? 'Editar Banner' : 'Nuevo Banner' ?>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $editBanner['id'] ?? 0 ?>">
            <input type="hidden" name="imagen_actual" id="ban_imagen_actual" value="<?= sanitize($editBanner['imagen'] ?? '') ?>">
            <input type="hidden" name="imagen_anterior" value="<?= sanitize($editBanner['imagen'] ?? '') ?>">

            <div class="ban-field">
                <label>Título *</label>
                <input type="text" name="titulo" value="<?= sanitize($editBanner['titulo'] ?? '') ?>"
                       placeholder="Ej: Cámaras hasta -30%" required>
            </div>

            <div class="ban-field">
                <label>Subtítulo</label>
                <input type="text" name="subtitulo" value="<?= sanitize($editBanner['subtitulo'] ?? '') ?>"
                       placeholder="Descripción breve del banner">
            </div>

            <div class="ban-grid-2">
                <div class="ban-field">
                    <label>Texto del Botón</label>
                    <input type="text" name="texto_boton" value="<?= sanitize($editBanner['texto_boton'] ?? 'Ver más') ?>">
                </div>
                <div class="ban-field">
                    <label>Orden</label>
                    <input type="number" name="orden" value="<?= (int)($editBanner['orden'] ?? 0) ?>" min="0">
                </div>
            </div>

            <div class="ban-field">
                <label>URL del Botón</label>
                <input type="text" name="url_boton" value="<?= sanitize($editBanner['url_boton'] ?? '') ?>"
                       placeholder="Ej: /productos.php?marca=HIKVISION">
            </div>

            <div class="ban-grid-2">
                <div class="ban-field">
                    <label>Color de Fondo</label>
                    <input type="color" name="color_fondo" value="<?= sanitize($editBanner['color_fondo'] ?? '#1a1a2e') ?>">
                </div>
                <div class="ban-field">
                    <label>Color de Texto</label>
                    <input type="color" name="color_texto" value="<?= sanitize($editBanner['color_texto'] ?? '#ffffff') ?>">
                </div>
            </div>

            <div class="ban-field">
                <label>Imagen (opcional, JPG/PNG/WEBP)</label>
                <div id="ban-img-preview">
                <?php if(!empty($editBanner['imagen'])): ?>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                        <img src="<?= SITE_URL ?>/<?= sanitize($editBanner['imagen']) ?>" class="ban-preview-img" style="margin-bottom:0;">
                        <button type="button" onclick="eliminarImagenBanner()"
                                style="padding:6px 12px;background:rgba(229,57,53,.1);color:#dc2626;border:1px solid rgba(229,57,53,.3);border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-trash"></i> Quitar
                        </button>
                    </div>
                <?php endif; ?>
                </div>
                <input type="file" name="imagen" id="ban_imagen_input" accept="image/*">
            </div>

            <label class="ban-check">
                <input type="checkbox" name="activo" <?= ($editBanner['activo'] ?? 1) ? 'checked' : '' ?>>
                Activo (visible en la página de inicio)
            </label>

            <div style="display:flex;gap:10px;">
                <button type="submit" class="ban-btn-save">
                    <i class="fas fa-save"></i> Guardar Banner
                </button>
                <button type="button" class="ban-btn-cancel"
                        onclick="document.getElementById('modal-banner').style.display='none'">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function eliminarBanner(id, url) {
    confirmar('¿Eliminar este banner?', function(ok) {
        if (ok) window.location.href = url;
    });
}
function eliminarImagenBanner() {
    confirmar('¿Quitar esta imagen?', function(ok) {
        if (!ok) return;
        document.getElementById('ban_imagen_actual').value = '';
        document.getElementById('ban-img-preview').innerHTML = '<span style="font-size:12px;color:#dc2626;"><i class="fas fa-check-circle"></i> Imagen quitada.</span>';
        var inp = document.getElementById('ban_imagen_input');
        if (inp) inp.value = '';
    });
}
</script>

<?php include '../includes/footer.php'; ?>