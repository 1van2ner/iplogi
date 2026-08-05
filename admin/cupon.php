<?php
require_once '../includes/config.php';

requireLogin();

if (!isAdmin()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$pageTitle = 'Gestión de Cupones';

$adminNombre = trim(
    ($_SESSION['nombre'] ?? 'Administrador') . ' ' .
    ($_SESSION['apellido'] ?? '')
);

$mensaje = '';
$errores = [];

$editId = null;
$codigo = '';
$tipo_cupon = '';
$tipo_descuento = 'porcentaje';
$descuento = '';
$fecha_inicio = '';
$fecha_fin = '';
$uso_maximo = '';
$compra_minima = '';
$descripcion = '';
$activo = 1;

function slugify($texto) {
    $texto = trim($texto);
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = preg_replace('/[^\p{L}\p{N}]+/u', '-', $texto);
    return trim($texto, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id']) && ctype_digit($_POST['delete_id'])) {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare('DELETE FROM cupones WHERE id = ?');
            $stmt->execute([(int)$_POST['delete_id']]);
            header('Location: cupon.php?msg=eliminado');
            exit;
        } catch (Exception $e) {
            $errores[] = 'Error al eliminar el cupón: ' . $e->getMessage();
        }
    } else {
        $editId = isset($_POST['edit_id']) && ctype_digit($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $tipo_cupon = trim($_POST['tipo_cupon'] ?? '');
        $tipo_descuento = trim($_POST['tipo_descuento'] ?? 'porcentaje');
        $descuento = trim($_POST['descuento'] ?? '');
        $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
        $fecha_fin = trim($_POST['fecha_fin'] ?? '');
        $uso_maximo = trim($_POST['uso_maximo'] ?? '');
        $compra_minima = trim($_POST['compra_minima'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;
    }

    if ($codigo === '') {
        $errores[] = 'El código del cupón es obligatorio.';
    }
    if ($tipo_cupon === '') {
        $errores[] = 'Selecciona el tipo de campaña.';
    }
    if ($descuento === '' || !is_numeric($descuento) || $descuento <= 0) {
        $errores[] = 'Ingresa un descuento válido.';
    }
    if ($fecha_inicio === '' || $fecha_fin === '') {
        $errores[] = 'Selecciona fecha de inicio y fecha de vencimiento.';
    }
    if ($fecha_inicio !== '' && $fecha_fin !== '' && $fecha_fin < $fecha_inicio) {
        $errores[] = 'La fecha de vencimiento no puede ser anterior a la fecha de inicio.';
    }
    if ($uso_maximo !== '' && (!ctype_digit($uso_maximo) || (int)$uso_maximo < 1)) {
        $errores[] = 'Límite de usos debe ser un número entero mayor o igual a 1.';
    }
    if ($compra_minima !== '' && !is_numeric($compra_minima)) {
        $errores[] = 'Compra mínima debe ser un número válido.';
    }
    if ($descripcion === '') {
        $errores[] = 'La descripción es obligatoria.';
    }

    if (empty($errores)) {
        try {
            $pdo = getDB();

            if ($editId) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM cupones WHERE codigo = ? AND id <> ?');
                $stmt->execute([$codigo, $editId]);
                if ($stmt->fetchColumn() > 0) {
                    $errores[] = 'Ya existe un cupón con ese código.';
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE cupones SET codigo = ?, tipo_campana = ?, tipo_descuento = ?, descuento = ?, fecha_inicio = ?, fecha_vencimiento = ?, limite_usos = ?, compra_minima = ?, descripcion = ?, activo = ? WHERE id = ?'
                    );
                    $stmt->execute([
                        $codigo,
                        $tipo_cupon,
                        $tipo_descuento,
                        $descuento,
                        $fecha_inicio,
                        $fecha_fin,
                        $uso_maximo !== '' ? $uso_maximo : null,
                        $compra_minima !== '' ? $compra_minima : null,
                        $descripcion,
                        $activo,
                        $editId,
                    ]);
                    header('Location: cupon.php?msg=actualizado');
                    exit;
                }
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM cupones WHERE codigo = ?');
                $stmt->execute([$codigo]);
                if ($stmt->fetchColumn() > 0) {
                    $errores[] = 'Ya existe un cupón con ese código.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO cupones (codigo, tipo_campana, tipo_descuento, descuento, fecha_inicio, fecha_vencimiento, limite_usos, compra_minima, descripcion, activo, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $stmt->execute([
                        $codigo,
                        $tipo_cupon,
                        $tipo_descuento,
                        $descuento,
                        $fecha_inicio,
                        $fecha_fin,
                        $uso_maximo !== '' ? $uso_maximo : null,
                        $compra_minima !== '' ? $compra_minima : null,
                        $descripcion,
                        $activo,
                    ]);
                    header('Location: cupon.php?msg=creado');
                    exit;
                }
            }
        } catch (Exception $e) {
            $errores[] = 'Error al guardar el cupón: ' . $e->getMessage();
        }
    }
}

try {
    $pdo = getDB();

    if (!$editId && isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $editId = (int)$_GET['edit'];
        $stmt = $pdo->prepare('SELECT * FROM cupones WHERE id = ?');
        $stmt->execute([$editId]);
        $cuponEdicion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cuponEdicion) {
            $codigo = $cuponEdicion['codigo'];
            $tipo_cupon = $cuponEdicion['tipo_campana'];
            $tipo_descuento = $cuponEdicion['tipo_descuento'] ?: 'porcentaje';
            $descuento = $cuponEdicion['descuento'];
            $fecha_inicio = $cuponEdicion['fecha_inicio'];
            $fecha_fin = $cuponEdicion['fecha_vencimiento'];
            $uso_maximo = $cuponEdicion['limite_usos'];
            $compra_minima = $cuponEdicion['compra_minima'];
            $descripcion = $cuponEdicion['descripcion'];
            $activo = $cuponEdicion['activo'] ? 1 : 0;
        } else {
            $editId = null;
        }
    }

    $cuponesDemo = $pdo->query('SELECT * FROM cupones ORDER BY creado_en DESC')->fetchAll();
    $totalCupones = count($cuponesDemo);
    $activosCupones = 0;
    foreach ($cuponesDemo as $cupon) {
        if (!empty($cupon['activo'])) {
            $activosCupones++;
        }
    }
} catch (Exception $e) {
    $cuponesDemo = [];
    $totalCupones = 0;
    $activosCupones = 0;
    $errores[] = 'No se pudo cargar la lista de cupones: ' . $e->getMessage();
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'creado') {
        $mensaje = 'Cupón creado correctamente.';
    } elseif ($_GET['msg'] === 'actualizado') {
        $mensaje = 'Cupón actualizado correctamente.';
    } elseif ($_GET['msg'] === 'eliminado') {
        $mensaje = 'Cupón eliminado correctamente.';
    }
}

include '../includes/header.php';
?>

<style>
:root {
    --amarillo-texto: #6b7300;
}

.coupon-page {
    padding: 28px 0 60px;
}

.coupon-layout {
    display: grid;
    grid-template-columns: 250px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}

.coupon-sidebar,
.coupon-card,
.coupon-stat {
    background: var(--bg2);
    border: 1.5px solid var(--borde);
    border-radius: var(--rl);
}

.coupon-sidebar {
    padding: 22px;
    position: sticky;
    top: 145px;
}

.admin-avatar-coupon {
    width: 72px;
    height: 72px;
    margin: 0 auto 12px;
    border-radius: 50%;
    background: var(--amarillo);
    color: #111;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    box-shadow: 0 0 0 4px rgba(237, 232, 42, .18);
}

.admin-name-coupon {
    color: var(--blanco);
    text-align: center;
    font-weight: 900;
    font-size: 16px;
}

.admin-role-coupon {
    color: var(--amarillo-texto);
    text-align: center;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    margin: 5px 0 18px;
}

.coupon-nav a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    margin-bottom: 3px;
    border-radius: var(--r);
    color: var(--gris2);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: .2s;
}

.coupon-nav a:hover,
.coupon-nav a.active {
    background: rgba(237, 232, 42, .12);
    color: var(--amarillo-texto);
}

.coupon-nav a.active {
    border-left: 3px solid var(--amarillo);
    padding-left: 9px;
}

.coupon-nav .sep {
    height: 1px;
    background: var(--borde);
    margin: 10px 0;
}

.coupon-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 22px;
}

.coupon-eyebrow {
    color: var(--amarillo-texto);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1px;
    margin-bottom: 5px;
}

.coupon-head h1 {
    color: var(--blanco);
    font-size: 24px;
    font-weight: 900;
    margin: 0;
}

.coupon-head p {
    color: var(--gris3);
    font-size: 13px;
    margin: 6px 0 0;
}

.btn-new-coupon,
.btn-save-coupon,
.btn-reset-coupon {
    border: none;
    border-radius: var(--r);
    padding: 11px 16px;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    transition: .2s;
}

.btn-new-coupon,
.btn-save-coupon {
    background: var(--amarillo);
    color: #111;
}

.btn-new-coupon:hover,
.btn-save-coupon:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
}

.btn-reset-coupon {
    background: var(--bg3);
    color: var(--gris2);
    border: 1px solid var(--borde);
}

.coupon-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 22px;
}

.coupon-stat {
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.coupon-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    background: rgba(237, 232, 42, .14);
    color: var(--amarillo-texto);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.coupon-stat-number {
    color: var(--blanco);
    font-size: 22px;
    font-weight: 900;
}

.coupon-stat-label {
    color: var(--gris3);
    font-size: 11px;
    margin-top: 2px;
}

.front-info,
.front-alert {
    padding: 12px 14px;
    border-radius: var(--r);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 20px;
}

.front-info {
    color: #a9c7ff;
    border: 1px solid rgba(66, 165, 245, .35);
    background: rgba(66, 165, 245, .1);
}

.front-alert {
    display: none;
}

.front-alert.show {
    display: flex;
}

.front-alert.success {
    color: #a5d6a7;
    background: rgba(67, 160, 71, .12);
    border: 1px solid rgba(67, 160, 71, .35);
}

.front-alert.error {
    color: #ff9e9e;
    background: rgba(229, 57, 53, .12);
    border: 1px solid rgba(229, 57, 53, .35);
}

.front-alert.info {
    color: #a9c7ff;
    background: rgba(66, 165, 245, .1);
    border: 1px solid rgba(66, 165, 245, .35);
}

.coupon-create-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.3fr) minmax(280px, .7fr);
    gap: 20px;
    margin-bottom: 22px;
}

.coupon-card {
    overflow: hidden;
}

.coupon-card-head {
    padding: 16px 18px;
    border-bottom: 1px solid var(--borde);
    display: flex;
    align-items: center;
    gap: 9px;
}

.coupon-card-head h2 {
    color: var(--blanco);
    font-size: 16px;
    font-weight: 900;
    margin: 0;
}

.coupon-card-head i {
    color: var(--amarillo-texto);
}

.coupon-card-body {
    padding: 18px;
}

.coupon-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}

.coupon-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.coupon-form .form-group.full {
    grid-column: 1 / -1;
}

.coupon-form label:not(.switch-coupon) {
    color: var(--gris2);
    font-size: 12px;
    font-weight: 800;
}

.coupon-form input,
.coupon-form select,
.coupon-form textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1.5px solid var(--borde);
    border-radius: var(--r);
    background: var(--bg3);
    color: var(--blanco);
    padding: 11px 12px;
    font-size: 13px;
    outline: none;
}

.coupon-form textarea {
    min-height: 86px;
    resize: vertical;
}

.coupon-form input:focus,
.coupon-form select:focus,
.coupon-form textarea:focus {
    border-color: var(--amarillo);
}

.help-text {
    color: var(--gris3);
    font-size: 11px;
}

.discount-field {
    display: flex;
}

.discount-field input {
    border-radius: var(--r) 0 0 var(--r);
}

.discount-symbol {
    min-width: 48px;
    background: rgba(237, 232, 42, .14);
    border: 1.5px solid var(--borde);
    border-left: none;
    border-radius: 0 var(--r) var(--r) 0;
    color: var(--amarillo-texto);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 13px;
    font-weight: 900;
}

.switch-coupon {
    margin-top: 6px;
    display: flex;
    align-items: center;
    gap: 9px;
    color: var(--gris2);
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
}

.switch-coupon input {
    width: 17px;
    height: 17px;
    accent-color: var(--amarillo);
}

.coupon-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    border-top: 1px solid var(--borde);
    margin-top: 20px;
    padding-top: 16px;
}

.preview-wrap {
    padding: 18px;
}

.coupon-preview {
    min-height: 365px;
    border-radius: 16px;
    padding: 22px;
    box-sizing: border-box;
    color: #fff;
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(circle at right top, rgba(237, 232, 42, .45), transparent 35%),
        linear-gradient(145deg, #242707, #111310);
}

.coupon-preview::after {
    content: '';
    position: absolute;
    width: 160px;
    height: 160px;
    border: 2px solid rgba(237, 232, 42, .2);
    border-radius: 50%;
    bottom: -80px;
    right: -55px;
}

.preview-brand {
    color: #e8e933;
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1.5px;
}

.preview-campaign {
    display: inline-block;
    margin-top: 25px;
    padding: 5px 10px;
    border-radius: 20px;
    background: rgba(255, 255, 255, .12);
    color: #fff;
    font-size: 11px;
    font-weight: 800;
}

.preview-code {
    color: #fff;
    font-size: 25px;
    font-weight: 900;
    letter-spacing: .8px;
    margin: 13px 0 20px;
    overflow-wrap: anywhere;
}

.preview-discount {
    display: flex;
    align-items: flex-start;
    gap: 3px;
    color: #e8e933;
}

.preview-discount strong {
    font-size: 52px;
    line-height: .9;
    font-weight: 900;
}

.preview-discount span {
    font-size: 22px;
    font-weight: 900;
}

.preview-description {
    min-height: 42px;
    color: rgba(255, 255, 255, .78);
    font-size: 13px;
    line-height: 1.45;
    margin: 20px 0;
}

.preview-info {
    border-top: 1px dashed rgba(255, 255, 255, .28);
    padding-top: 14px;
    display: grid;
    gap: 8px;
    font-size: 11px;
    color: rgba(255, 255, 255, .7);
}

.preview-info div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.preview-info strong {
    color: #fff;
}

.table-wrap {
    overflow-x: auto;
}

.coupon-table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
}

.coupon-table th {
    color: var(--gris3);
    font-size: 11px;
    text-transform: uppercase;
    text-align: left;
    background: var(--bg3);
    padding: 12px 14px;
}

.coupon-table td {
    color: var(--gris2);
    font-size: 13px;
    border-top: 1px solid var(--borde);
    padding: 13px 14px;
}

.coupon-code {
    display: inline-block;
    background: rgba(237, 232, 42, .12);
    border: 1px dashed rgba(237, 232, 42, .5);
    border-radius: 6px;
    color: var(--amarillo-texto);
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .5px;
    padding: 5px 8px;
}

.coupon-table small {
    display: block;
    color: var(--gris3);
    font-size: 10px;
    margin-top: 3px;
}

.event-chip,
.coupon-status {
    border-radius: 20px;
    display: inline-block;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 9px;
}

.event-navidad {
    background: rgba(229, 57, 53, .16);
    color: #ff8a80;
}

.event-aniversario {
    background: rgba(237, 232, 42, .16);
    color: var(--amarillo-texto);
}

.event-cyber {
    background: rgba(41, 182, 246, .16);
    color: #81d4fa;
}

.event-otro {
    background: rgba(171, 71, 188, .16);
    color: #ce93d8;
}

.status-active {
    background: rgba(67, 160, 71, .18);
    color: #81c784;
}

.status-inactive {
    background: rgba(229, 57, 53, .16);
    color: #ff8a80;
}

.table-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.table-action {
    min-width: 90px;
    height: 36px;
    padding: 0 12px;
    border: 1px solid rgba(255,255,255,.12);
    border-radius: 12px;
    background: rgba(255,255,255,.05);
    color: var(--gris2);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    transition: all .18s ease;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.04);
    white-space: nowrap;
}

.table-action:hover {
    color: var(--amarillo-texto);
    border-color: var(--amarillo);
    background: rgba(237,232,42,.12);
    transform: translateY(-1px);
}

.table-action i {
    font-size: 14px;
}

.table-action .action-label {
    display: inline-block;
    font-size: 12px;
    font-weight: 700;
    line-height: 1;
}

@media (max-width: 740px) {
    .table-actions {
        gap: 6px;
    }
    .table-action {
        min-width: 0;
        width: auto;
        padding: 0 10px;
    }
    .table-action .action-label {
        display: none;
    }
}

.table-action .action-label {
    display: inline-block;
    margin-left: 6px;
    font-size: 12px;
    font-weight: 700;
    color: inherit;
    opacity: 0.9;
}

.table-action-delete {
    border-color: rgba(229,57,53,.15);
}

.table-action-delete:hover {
    border-color: #ef5350;
    background: rgba(239,83,80,.12);
    color: #ef5350;
}

@media (max-width: 950px) {
    .coupon-layout {
        grid-template-columns: 1fr;
    }

    .coupon-sidebar {
        position: static;
    }

    .coupon-nav {
        display: flex;
        overflow-x: auto;
        gap: 4px;
    }

    .coupon-nav a {
        flex: 0 0 auto;
    }

    .coupon-nav .sep {
        display: none;
    }
}

@media (max-width: 700px) {
    .coupon-head,
    .coupon-create-grid {
        grid-template-columns: 1fr;
    }

    .coupon-head {
        flex-direction: column;
    }

    .coupon-stats,
    .coupon-form-grid {
        grid-template-columns: 1fr;
    }

    .coupon-form .form-group.full {
        grid-column: auto;
    }

    .coupon-actions {
        flex-direction: column-reverse;
    }

    .coupon-actions button {
        justify-content: center;
    }
}
</style>

<div class="container coupon-page">
    <div class="coupon-layout">

        <aside class="coupon-sidebar">
            <div class="admin-avatar-coupon">
                <i class="fas fa-user-shield"></i>
            </div>

            <div class="admin-name-coupon">
                <?= sanitize($adminNombre ?: 'Administrador') ?>
            </div>

            <div class="admin-role-coupon">
                Panel administrativo
            </div>

            <nav class="coupon-nav">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>

                <a href="index.php?tab=productos">
                    <i class="fas fa-box"></i> Productos
                </a>

                <a href="index.php?tab=usuarios">
                    <i class="fas fa-users"></i> Usuarios
                </a>

                <a href="promociones.php">
                    <i class="fas fa-fire"></i> Promociones
                </a>

                <a href="cupon.php" class="active">
                    <i class="fas fa-ticket-alt"></i> Cupones
                </a>

                <a href="index.php?tab=pedidos">
                    <i class="fas fa-shopping-bag"></i> Pedidos
                </a>

                <div class="sep"></div>

                <a href="categorias.php">
                    <i class="fas fa-th-large"></i> Categorías
                </a>

                <a href="reportes.php">
                    <i class="fas fa-chart-bar"></i> Reportes
                </a>
            </nav>
        </aside>

        <main>
            <div class="coupon-head">
                <div>
                    <div class="coupon-eyebrow">MARKETING Y PROMOCIONES</div>
                    <h1><i class="fas fa-ticket-alt" style="color:var(--amarillo-texto);"></i> Gestión de cupones</h1>
                    <p>Crea descuentos especiales para Navidad, aniversarios, Cyber Days y más.</p>
                </div>

                <button
                    type="button"
                    class="btn-new-coupon"
                    onclick="document.getElementById('formulario-cupon').scrollIntoView({behavior:'smooth'})"
                >
                    <i class="fas fa-plus"></i> Nuevo cupón
                </button>
            </div>

            <?php if ($mensaje): ?>
                <div class="front-alert show success" aria-live="polite">
                    <i class="fas fa-check-circle"></i>
                    <span><?= sanitize($mensaje) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errores)): ?>
                <div class="front-alert show error" aria-live="polite">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= sanitize(implode(' ', $errores)) ?></span>
                </div>
            <?php endif; ?>

            <div class="coupon-stats">
                <div class="coupon-stat">
                    <div class="coupon-stat-icon"><i class="fas fa-ticket-alt"></i></div>
                    <div>
                        <div class="coupon-stat-number" id="contador-total"><?= sanitize($totalCupones) ?></div>
                        <div class="coupon-stat-label">Cupones visibles</div>
                    </div>
                </div>

                <div class="coupon-stat">
                    <div class="coupon-stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div>
                        <div class="coupon-stat-number" id="contador-activos"><?= sanitize($activosCupones) ?></div>
                        <div class="coupon-stat-label">Cupones activos</div>
                    </div>
                </div>

                <div class="coupon-stat">
                    <div class="coupon-stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="coupon-stat-number">7</div>
                        <div class="coupon-stat-label">Tipos de campaña</div>
                    </div>
                </div>
            </div>

            <div class="front-info">
                <i class="fas fa-info-circle"></i>
                Los cupones se guardan en la base de datos y pueden editarse o eliminarse desde esta página.
            </div>

            <div id="aviso-front" class="front-alert" aria-live="polite">
                <i class="fas fa-check-circle"></i>
                <span></span>
            </div>

            <div class="coupon-create-grid">
                <section class="coupon-card" id="formulario-cupon">
                    <div class="coupon-card-head">
                        <i class="fas fa-plus-circle"></i>
                        <h2><?= $editId ? 'Editar cupón' : 'Crear nuevo cupón' ?></h2>
                    </div>

                    <div class="coupon-card-body">
                        <form id="form-cupon" class="coupon-form" method="post" action="cupon.php">
                            <input type="hidden" name="edit_id" value="<?= $editId ? (int)$editId : '' ?>">
                            <div class="coupon-form-grid">

                                <div class="form-group">
                                    <label for="codigo">Código del cupón *</label>
                                    <input
                                        type="text"
                                        id="codigo"
                                        name="codigo"
                                        maxlength="30"
                                        placeholder="Ej.: NAVIDAD2026"
                                        autocomplete="off"
                                        required
                                        value="<?= sanitize($codigo) ?>"
                                    >
                                    <span class="help-text">Se convertirá automáticamente a mayúsculas.</span>
                                </div>

                                <div class="form-group">
                                    <label for="tipo_cupon">Tipo de campaña *</label>
                                    <select id="tipo_cupon" name="tipo_cupon" required>
                                        <option value="">Selecciona una opción</option>
                                        <option value="Navidad" <?= $tipo_cupon === 'Navidad' ? 'selected' : '' ?>>Navidad</option>
                                        <option value="Año Nuevo" <?= $tipo_cupon === 'Año Nuevo' ? 'selected' : '' ?>>Año Nuevo</option>
                                        <option value="Aniversario de empresa" <?= $tipo_cupon === 'Aniversario de empresa' ? 'selected' : '' ?>>Aniversario de empresa</option>
                                        <option value="Cumpleaños" <?= $tipo_cupon === 'Cumpleaños' ? 'selected' : '' ?>>Cumpleaños</option>
                                        <option value="Cyber Days" <?= $tipo_cupon === 'Cyber Days' ? 'selected' : '' ?>>Cyber Days</option>
                                        <option value="Black Friday" <?= $tipo_cupon === 'Black Friday' ? 'selected' : '' ?>>Black Friday</option>
                                        <option value="Campaña especial" <?= $tipo_cupon === 'Campaña especial' ? 'selected' : '' ?>>Campaña especial</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="tipo_descuento">Tipo de descuento *</label>
                                    <select id="tipo_descuento" name="tipo_descuento">
                                        <option value="porcentaje" <?= $tipo_descuento === 'porcentaje' ? 'selected' : '' ?>>Porcentaje (%)</option>
                                        <option value="monto" <?= $tipo_descuento === 'monto' ? 'selected' : '' ?>>Monto fijo (S/)</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label id="label-descuento" for="descuento">Descuento (%) *</label>
                                    <div class="discount-field">
                                        <input
                                            type="number"
                                            id="descuento"
                                            name="descuento"
                                            min="1"
                                            max="100"
                                            step="0.01"
                                            placeholder="Ej.: 20"
                                            required
                                            value="<?= sanitize($descuento) ?>"
                                        >
                                        <span class="discount-symbol" id="simbolo-descuento">%</span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="fecha_inicio">Fecha de inicio *</label>
                                    <input type="date" id="fecha_inicio" name="fecha_inicio" required value="<?= sanitize($fecha_inicio) ?>">
                                </div>

                                <div class="form-group">
                                    <label for="fecha_fin">Fecha de vencimiento *</label>
                                    <input type="date" id="fecha_fin" name="fecha_fin" required value="<?= sanitize($fecha_fin) ?>">
                                </div>

                                <div class="form-group">
                                    <label for="uso_maximo">Límite de usos</label>
                                    <input
                                        type="number"
                                        id="uso_maximo"
                                        name="uso_maximo"
                                        min="1"
                                        placeholder="Déjalo vacío si no tiene límite"
                                        value="<?= sanitize($uso_maximo) ?>"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="compra_minima">Compra mínima (opcional)</label>
                                    <input
                                        type="number"
                                        id="compra_minima"
                                        name="compra_minima"
                                        min="0"
                                        step="0.01"
                                        placeholder="Ej.: 500"
                                        value="<?= sanitize($compra_minima) ?>"
                                    >
                                </div>

                                <div class="form-group full">
                                    <label for="descripcion">Descripción general *</label>
                                    <textarea
                                        id="descripcion"
                                        name="descripcion"
                                        placeholder="Ej.: Obtén 20% de descuento en televisores, laptops y celulares por campaña navideña."
                                        required
                                    ><?= sanitize($descripcion) ?></textarea>
                                </div>

                                <div class="form-group full">
                                    <label class="switch-coupon">
                                        <input type="checkbox" id="activo" name="activo" <?= $activo ? 'checked' : '' ?>>
                                        Activar este cupón apenas sea creado
                                    </label>
                                </div>
                            </div>

                            <div class="coupon-actions">
                                <button type="reset" class="btn-reset-coupon">
                                    <i class="fas fa-eraser"></i> Limpiar
                                </button>

                                <button type="submit" class="btn-save-coupon">
                                    <i class="fas fa-save"></i> <?= $editId ? 'Guardar cambios' : 'Crear cupón' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="coupon-card">
                    <div class="coupon-card-head">
                        <i class="fas fa-eye"></i>
                        <h2>Vista previa</h2>
                    </div>

                    <div class="preview-wrap">
                        <div class="coupon-preview">
                            <div class="preview-brand">ELECTROHOGAR</div>

                            <div class="preview-campaign" id="preview-campania">
                                Campaña especial
                            </div>

                            <div class="preview-code" id="preview-codigo">
                                MI-CUPON
                            </div>

                            <div class="preview-discount">
                                <strong id="preview-descuento">0</strong>
                                <span id="preview-simbolo">%</span>
                            </div>

                            <p class="preview-description" id="preview-descripcion">
                                Aquí aparecerá la descripción del cupón que escribas.
                            </p>

                            <div class="preview-info">
                                <div>
                                    <span>Válido desde</span>
                                    <strong id="preview-inicio">Sin fecha</strong>
                                </div>

                                <div>
                                    <span>Válido hasta</span>
                                    <strong id="preview-fin">Sin fecha</strong>
                                </div>

                                <div>
                                    <span>Estado</span>
                                    <strong id="preview-estado">Activo</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <section class="coupon-card">
                <div class="coupon-card-head">
                    <i class="fas fa-list"></i>
                    <h2>Cupones creados</h2>
                </div>

                <div class="table-wrap">
                    <table class="coupon-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Campaña</th>
                                <th>Descuento</th>
                                <th>Vigencia</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody id="tabla-cupones">
                            <?php foreach ($cuponesDemo as $cupon): ?>
                            <?php
                                $descuentoTexto = $cupon['tipo_descuento'] === 'monto'
                                    ? 'S/ ' . number_format((float)$cupon['descuento'], 2)
                                    : number_format((float)$cupon['descuento'], 2) . '%';
                                $tipoTexto = $cupon['tipo_descuento'] === 'monto' ? 'Monto fijo' : 'Porcentaje';
                                $inicioTexto = $cupon['fecha_inicio'] ? date('d/m/Y', strtotime($cupon['fecha_inicio'])) : 'Sin fecha';
                                $finTexto = $cupon['fecha_vencimiento'] ? date('d/m/Y', strtotime($cupon['fecha_vencimiento'])) : 'Sin fecha';
                                $estadoTexto = $cupon['activo'] ? 'Activo' : 'Inactivo';
                                $estadoClase = $cupon['activo'] ? 'status-active' : 'status-inactive';
                                $campaniaClass = slugify($cupon['tipo_campana'] ?: 'otro');
                            ?>
                            <tr>
                                <td>
                                    <span class="coupon-code"><?= sanitize($cupon['codigo']) ?></span>
                                </td>

                                <td>
                                    <span class="event-chip event-<?= $campaniaClass ?>">
                                        <?= sanitize($cupon['tipo_campana']) ?>
                                    </span>
                                </td>

                                <td>
                                    <strong style="color:var(--blanco);"><?= sanitize($descuentoTexto) ?></strong>
                                    <small><?= sanitize($tipoTexto) ?></small>
                                </td>

                                <td>
                                    <?= sanitize($inicioTexto) ?>
                                    <small>Hasta <?= sanitize($finTexto) ?></small>
                                </td>

                                <td>
                                    <span class="coupon-status <?= $estadoClase ?>">
                                        <?= sanitize($estadoTexto) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="table-actions">
                                        <a href="cupon.php?edit=<?= (int)$cupon['id'] ?>" class="table-action" title="Editar cupón">
                                            <i class="fas fa-edit"></i>
                                            <span class="action-label">Editar</span>
                                        </a>

                                        <form method="post" action="cupon.php" style="display:inline;">
                                            <input type="hidden" name="delete_id" value="<?= (int)$cupon['id'] ?>">
                                            <button type="submit" class="table-action table-action-delete" title="Eliminar cupón" onclick="return confirm('¿Eliminar este cupón?');">
                                                <i class="fas fa-trash"></i>
                                                <span class="action-label">Borrar</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
const formCupon = document.getElementById('form-cupon');
const inputCodigo = document.getElementById('codigo');
const selectCampania = document.getElementById('tipo_cupon');
const selectTipoDescuento = document.getElementById('tipo_descuento');
const inputDescuento = document.getElementById('descuento');
const inputDescripcion = document.getElementById('descripcion');
const inputInicio = document.getElementById('fecha_inicio');
const inputFin = document.getElementById('fecha_fin');
const inputActivo = document.getElementById('activo');

function escaparHTML(texto) {
    return String(texto).replace(/[&<>"']/g, function(caracter) {
        const caracteres = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return caracteres[caracter];
    });
}

function formatearFecha(fecha) {
    if (!fecha) {
        return 'Sin fecha';
    }

    const partes = fecha.split('-');

    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function actualizarTipoDescuento() {
    const esPorcentaje = selectTipoDescuento.value === 'porcentaje';

    document.getElementById('label-descuento').textContent =
        esPorcentaje ? 'Descuento (%) *' : 'Descuento (S/) *';

    document.getElementById('simbolo-descuento').textContent =
        esPorcentaje ? '%' : 'S/';

    document.getElementById('preview-simbolo').textContent =
        esPorcentaje ? '%' : 'S/';

    if (esPorcentaje) {
        inputDescuento.max = 100;
        inputDescuento.placeholder = 'Ej.: 20';
    } else {
        inputDescuento.removeAttribute('max');
        inputDescuento.placeholder = 'Ej.: 50';
    }
}

function actualizarVistaPrevia() {
    const codigo = inputCodigo.value.trim() || 'MI-CUPON';
    const campania = selectCampania.value || 'Campaña especial';
    const descuento = inputDescuento.value || '0';
    const descripcion = inputDescripcion.value.trim() ||
        'Aquí aparecerá la descripción del cupón que escribas.';

    document.getElementById('preview-codigo').textContent = codigo;
    document.getElementById('preview-campania').textContent = campania;
    document.getElementById('preview-descuento').textContent = descuento;
    document.getElementById('preview-descripcion').textContent = descripcion;
    document.getElementById('preview-inicio').textContent = formatearFecha(inputInicio.value);
    document.getElementById('preview-fin').textContent = formatearFecha(inputFin.value);
    document.getElementById('preview-estado').textContent =
        inputActivo.checked ? 'Activo' : 'Inactivo';
}

function mostrarAviso(mensaje, tipo = 'success') {
    const aviso = document.getElementById('aviso-front');

    aviso.className = 'front-alert show ' + tipo;
    aviso.querySelector('span').textContent = mensaje;

    aviso.querySelector('i').className =
        tipo === 'error'
            ? 'fas fa-exclamation-circle'
            : 'fas fa-check-circle';

    setTimeout(function() {
        aviso.classList.remove('show');
    }, 5000);
}

function actualizarContadores() {
    const filas = document.querySelectorAll('#tabla-cupones tr').length;
    const activos = document.querySelectorAll(
        '#tabla-cupones .status-active'
    ).length;

    document.getElementById('contador-total').textContent = filas;
    document.getElementById('contador-activos').textContent = activos;
}

function mostrarAccionPendiente() {
    mostrarAviso(
        'La edición y eliminación real las conectaremos cuando hagamos la base de datos de cupones.',
        'info'
    );
}

inputCodigo.addEventListener('input', function() {
    this.value = this.value
        .toUpperCase()
        .replace(/[^A-Z0-9_-]/g, '');

    actualizarVistaPrevia();
});

selectCampania.addEventListener('change', actualizarVistaPrevia);
selectTipoDescuento.addEventListener('change', function() {
    actualizarTipoDescuento();
    actualizarVistaPrevia();
});

inputDescuento.addEventListener('input', actualizarVistaPrevia);
inputDescripcion.addEventListener('input', actualizarVistaPrevia);
inputInicio.addEventListener('change', actualizarVistaPrevia);
inputFin.addEventListener('change', actualizarVistaPrevia);
inputActivo.addEventListener('change', actualizarVistaPrevia);

formCupon.addEventListener('reset', function() {
    setTimeout(function() {
        actualizarTipoDescuento();
        actualizarVistaPrevia();
    }, 0);
});

formCupon.addEventListener('submit', function(evento) {
    const inicio = inputInicio.value;
    const fin = inputFin.value;

    if (inicio && fin && fin < inicio) {
        evento.preventDefault();
        mostrarAviso(
            'La fecha de vencimiento no puede ser anterior a la fecha de inicio.',
            'error'
        );
    }
});

actualizarTipoDescuento();
actualizarVistaPrevia();
</script>

<?php include '../includes/footer.php'; ?>