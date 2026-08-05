<?php
require_once '../includes/config.php';
requireLogin();
if (!isAdmin()) { header('Location: ' . SITE_URL . '/index.php'); exit; }
$pageTitle = 'Categorías';
$pdo = getDB();

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'delete') {
        $delId = (int)($_POST['del_id'] ?? 0);
        if ($delId > 0) {
            $parentRow = $pdo->prepare("SELECT padre_id FROM categorias WHERE id=?");
            $parentRow->execute([$delId]);
            $parentData = $parentRow->fetch();
            $newParent = $parentData ? $parentData['padre_id'] : null;
            $pdo->prepare("UPDATE categorias SET padre_id=? WHERE padre_id=?")->execute([$newParent, $delId]);
            $pdo->prepare("DELETE FROM categorias WHERE id=?")->execute([$delId]);
            $msg = 'Categoría eliminada correctamente.';
        }
    } else {
        $cid      = (int)($_POST['id'] ?? 0);
        $nombre   = sanitize(trim($_POST['nombre'] ?? ''));
        $desc     = sanitize(trim($_POST['descripcion'] ?? ''));
        $icono    = sanitize(trim($_POST['icono'] ?? 'fa-tag'));
        $padreRaw = $_POST['padre_id'] ?? '';
        $padre_id = ($padreRaw !== '' && (int)$padreRaw > 0) ? (int)$padreRaw : null;

        if ($cid > 0) {
            if ($padre_id !== $cid) {
                $pdo->prepare("UPDATE categorias SET nombre=?,descripcion=?,icono=?,padre_id=? WHERE id=?")
                    ->execute([$nombre, $desc, $icono, $padre_id, $cid]);
                $msg = 'Categoría actualizada correctamente.';
            } else {
                $msg = 'Una categoría no puede ser su propio padre.';
                $msgType = 'danger';
            }
        } else {
            $pdo->prepare("INSERT INTO categorias (nombre,descripcion,icono,padre_id) VALUES (?,?,?,?)")
                ->execute([$nombre, $desc, $icono, $padre_id]);
            $msg = 'Categoría creada correctamente.';
        }
    }
}

$editCat = null;
if (isset($_GET['edit'])) {
    $ep = $pdo->prepare("SELECT * FROM categorias WHERE id=?");
    $ep->execute([(int)$_GET['edit']]);
    $editCat = $ep->fetch();
}

$allCats = $pdo->query("
    SELECT c.*, COUNT(p.id) as n_productos
    FROM categorias c
    LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
    GROUP BY c.id
    ORDER BY COALESCE(c.padre_id, c.id), c.padre_id IS NOT NULL, c.nombre
")->fetchAll(PDO::FETCH_ASSOC);

$catById = [];
foreach ($allCats as $c) {
    $catById[$c['id']] = $c;
    $catById[$c['id']]['children'] = [];
}
$roots = [];
foreach ($catById as $id => &$c) {
    if ($c['padre_id'] === null) {
        $roots[] = &$c;
    } else {
        if (isset($catById[$c['padre_id']])) {
            $catById[$c['padre_id']]['children'][] = &$c;
        } else {
            $roots[] = &$c;
        }
    }
}
unset($c);

function renderCategoria($c, $nivel = 0) {
    $indent = $nivel * 30;
    $bgColors = ['#f8f9fa', '#ffffff', '#f0f4ff', '#fff8f0'];
    $bg = $bgColors[$nivel % count($bgColors)];
    $borderL = $nivel > 0 ? 'border-left:3px solid #dee2e6;' : '';
    ?>
    <div style="margin-left:<?= $indent ?>px;margin-bottom:6px;background:<?= $bg ?>;border:1px solid #e0e0e0;<?= $borderL ?>border-radius:8px;display:flex;align-items:center;padding:10px 14px;gap:12px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
        <span style="color:#bbb;font-size:13px;cursor:grab;flex-shrink:0;"><i class="fas fa-grip-vertical"></i></span>
        <div style="width:40px;height:40px;border:1px solid #ddd;border-radius:6px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas <?= sanitize($c['icono'] ?: 'fa-tag') ?>" style="color:#aaa;font-size:16px;"></i>
        </div>
        <div style="flex:1;font-size:14px;font-weight:<?= $nivel === 0 ? '700' : '500' ?>;color:#333;">
            <?= sanitize($c['nombre']) ?>
            <span style="font-size:11px;font-weight:400;color:#999;margin-left:6px;"><?= $c['n_productos'] ?> productos</span>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0;">
            <a href="categorias.php?edit=<?= $c['id'] ?>" style="width:28px;height:28px;border-radius:5px;background:#e8f0fe;color:#1a73e8;display:flex;align-items:center;justify-content:center;font-size:12px;text-decoration:none;" title="Editar"><i class="fas fa-edit"></i></a>
            <a href="<?= SITE_URL ?>/productos.php?categoria=<?= $c['id'] ?>" target="_blank" style="width:28px;height:28px;border-radius:5px;background:#e6f4ea;color:#34a853;display:flex;align-items:center;justify-content:center;font-size:12px;text-decoration:none;" title="Ver productos"><i class="fas fa-eye"></i></a>
            <?php if (empty($c['children']) && $c['n_productos'] == 0): ?>
            <form method="POST" style="margin:0;" id="form-del-cat-<?= $c['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="del_id" value="<?= $c['id'] ?>">
                <button type="button"
                        onclick="eliminarCategoria(<?= $c['id'] ?>, '<?= sanitize(addslashes($c['nombre'])) ?>')"
                        style="width:28px;height:28px;border-radius:5px;background:#fce8e6;color:#ea4335;border:none;cursor:pointer;font-size:12px;display:flex;align-items:center;justify-content:center;" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    if (!empty($c['children'])) {
        foreach ($c['children'] as $hijo) {
            renderCategoria($hijo, $nivel + 1);
        }
    }
}

function renderSelectHijos($children, $currentPadreId, $editId, $nivel) {
    foreach ($children as $c) {
        if ($c['id'] == $editId) continue;
        $pad = str_repeat('— ', $nivel);
        $sel = ($currentPadreId == $c['id']) ? ' selected' : '';
        echo '<option value="' . $c['id'] . '"' . $sel . '>' . $pad . sanitize($c['nombre']) . '</option>';
        if (!empty($c['children'])) {
            renderSelectHijos($c['children'], $currentPadreId, $editId, $nivel + 1);
        }
    }
}

include '../includes/header.php';
?>

<div class="container" style="padding:24px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h1 style="font-size:22px;font-weight:800;"><i class="fas fa-th-large" style="color:var(--primary);"></i> Categorías</h1>
            <div style="font-size:13px;color:var(--gray);margin-top:2px;"><a href="index.php">Dashboard</a> › Categorías</div>
        </div>
        <button onclick="openNewCategory()"
                style="padding:10px 20px;background:var(--primary);color:white;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:700;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-plus"></i> Nueva Categoría
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msgType ?>" style="margin-bottom:16px;">
            <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> <?= $msg ?>
        </div>
    <?php endif; ?>

    <div style="font-size:12px;color:#888;margin-bottom:12px;">
        <i class="fas fa-info-circle"></i> Las subcategorías aparecen con sangría debajo de su categoría padre.
    </div>

    <div style="background:white;border-radius:var(--radius-lg);border:1px solid var(--border);padding:16px;">
        <?php if (empty($roots)): ?>
            <p style="text-align:center;color:var(--gray);padding:40px 0;">No hay categorías. Crea la primera.</p>
        <?php else: ?>
            <?php foreach ($roots as $root): ?>
                <?php renderCategoria($root, 0); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL -->
<div id="modal-cat" style="display:<?= $editCat ? 'flex' : 'none' ?>;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
    <div style="background:white;border-radius:var(--radius-lg);padding:28px;width:100%;max-width:480px;position:relative;max-height:90vh;overflow-y:auto;">
        <button onclick="document.getElementById('modal-cat').style.display='none'" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--gray);">×</button>
        <h2 style="font-size:18px;font-weight:800;margin-bottom:20px;"><?= $editCat ? 'Editar Categoría' : 'Nueva Categoría' ?></h2>
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editCat ? $editCat['id'] : 0 ?>">

            <div class="form-group">
                <label>Nombre *</label>
                <input type="text" name="nombre" value="<?= sanitize($editCat['nombre'] ?? '') ?>" required placeholder="Ej: Cámaras IP">
            </div>

            <div class="form-group">
                <label>Categoría padre <small style="color:var(--gray);">(vacío = categoría principal)</small></label>
                <select name="padre_id" style="width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius);font-size:14px;">
                    <option value="">— Sin padre (categoría principal) —</option>
                    <?php
                    $currentPadreId = $editCat['padre_id'] ?? null;
                    foreach ($roots as $r) {
                        if ($r['id'] == ($editCat['id'] ?? 0)) continue;
                        $sel = ($currentPadreId == $r['id']) ? ' selected' : '';
                        echo '<option value="' . $r['id'] . '"' . $sel . '>' . sanitize($r['nombre']) . '</option>';
                        renderSelectHijos($r['children'] ?? [], $currentPadreId, $editCat['id'] ?? 0, 1);
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Ícono Font Awesome</label>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" name="icono" id="icono-input" value="<?= sanitize($editCat['icono'] ?? 'fa-tag') ?>" placeholder="Ej: fa-camera" style="flex:1;" oninput="updateIconPreview(this.value)">
                    <div id="icon-preview" style="width:38px;height:38px;border:1.5px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--primary);flex-shrink:0;">
                        <i class="fas <?= sanitize($editCat['icono'] ?? 'fa-tag') ?>"></i>
                    </div>
                </div>
                <small style="color:var(--gray);font-size:12px;">Usa clases de <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--primary);">Font Awesome</a> (ej: fa-camera, fa-wifi)</small>
            </div>

            <div class="form-group">
                <label>Descripción</label>
                <textarea name="descripcion" rows="3" placeholder="Descripción..."><?= sanitize($editCat['descripcion'] ?? '') ?></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px;">
                <button type="button" onclick="document.getElementById('modal-cat').style.display='none'"
                        style="padding:10px 18px;background:var(--light);border:1.5px solid var(--border);border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:600;">Cancelar</button>
                <button type="submit" style="padding:10px 22px;background:var(--primary);color:white;border:none;border-radius:var(--radius);cursor:pointer;font-size:14px;font-weight:700;">
                    <i class="fas fa-save"></i> <?= $editCat ? 'Actualizar' : 'Crear' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function updateIconPreview(val) {
    var prev = document.getElementById('icon-preview');
    if (prev) prev.innerHTML = '<i class="fas ' + val + '"></i>';
}

function openNewCategory() {
    var form = document.querySelector('#modal-cat form');
    if (!form) return;
    form.reset();
    form.querySelector('[name="id"]').value = 0;
    form.querySelector('[name="action"]').value = 'save';
    form.querySelector('[name="icono"]').value = 'fa-tag';
    var iconPreview = document.getElementById('icon-preview');
    if (iconPreview) iconPreview.innerHTML = '<i class="fas fa-tag"></i>';
    document.querySelector('#modal-cat h2').textContent = 'Nueva Categoría';
    document.getElementById('modal-cat').style.display = 'flex';
}

// ── Eliminar categoría con modal confirmar() del footer ───────
function eliminarCategoria(id, nombre) {
    confirmar('¿Eliminar «' + nombre + '»?', function(ok) {
        if (ok) document.getElementById('form-del-cat-' + id).submit();
    });
}
</script>

<?php include '../includes/footer.php'; ?>