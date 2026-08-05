<?php
require_once 'includes/config.php';
if (!isLoggedIn()) { header('Location: ' . SITE_URL . '/login.php'); exit; }
$pdo = getDB();
$msg = ''; $msgType = 'success';

// ── ACCIONES POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_perfil') {
        $nombre    = sanitize(trim($_POST['nombre']    ?? ''));
        $apellido  = sanitize(trim($_POST['apellido']  ?? ''));
        $celular   = sanitize(trim($_POST['celular']   ?? ''));
        $direccion = sanitize(trim($_POST['direccion'] ?? ''));
        if ($nombre) {
            $pdo->prepare("UPDATE usuarios SET nombre=?,apellido=?,telefono=?,celular=?,direccion_entrega=? WHERE id=?")
                ->execute([$nombre,$apellido,$celular,$celular,$direccion,$_SESSION['usuario_id']]);
            $_SESSION['nombre']   = $nombre;
            $_SESSION['apellido'] = $apellido;
            $msg = 'Perfil actualizado correctamente.';
        }
    }

    elseif ($action === 'change_pass') {
        $actual = $_POST['pass_actual'] ?? '';
        $nueva  = $_POST['pass_nueva']  ?? '';
        $nueva2 = $_POST['pass_nueva2'] ?? '';
        $s = $pdo->prepare("SELECT password FROM usuarios WHERE id=?");
        $s->execute([$_SESSION['usuario_id']]);
        $hash = $s->fetchColumn();
        if (!password_verify($actual, $hash))   { $msg = 'La contraseña actual es incorrecta.';          $msgType = 'error'; }
        elseif (strlen($nueva) < 6)             { $msg = 'La nueva contraseña debe tener mínimo 6 caracteres.'; $msgType = 'error'; }
        elseif ($nueva !== $nueva2)             { $msg = 'Las contraseñas no coinciden.';                $msgType = 'error'; }
        else {
            $pdo->prepare("UPDATE usuarios SET password=? WHERE id=?")
                ->execute([password_hash($nueva, PASSWORD_DEFAULT), $_SESSION['usuario_id']]);
            $msg = 'Contraseña cambiada correctamente.';
        }
    }
}

// ── DATOS ─────────────────────────────────────────────────────
$s = $pdo->prepare("SELECT * FROM usuarios WHERE id=?");
$s->execute([$_SESSION['usuario_id']]);
$user = $s->fetch();

$s = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id=?");
$s->execute([$_SESSION['usuario_id']]);
$totalPedidos = (int)$s->fetchColumn();

$s = $pdo->prepare("SELECT * FROM pedidos WHERE usuario_id=? ORDER BY creado_en DESC LIMIT 5");
$s->execute([$_SESSION['usuario_id']]);
$ultimosPedidos = $s->fetchAll();

$tab = $_GET['tab'] ?? 'datos';
$pageTitle = 'Mi Perfil';
include 'includes/header.php';
?>

<div class="container" style="padding:30px 20px 60px;">

  <?php if($msg): ?>
  <div class="alert alert-<?= $msgType==='error'?'error':'success' ?>" style="margin-bottom:20px;">
    <i class="fas fa-<?= $msgType==='error'?'times-circle':'check-circle' ?>"></i> <?= $msg ?>
  </div>
  <?php endif; ?>

  <div class="profile-layout">

    <!-- ── SIDEBAR ──────────────────────────────────────────── -->
    <div class="profile-sidebar">

      <!-- Avatar -->
      <div class="profile-avatar">
        <i class="fas fa-user" style="font-size:32px;"></i>
      </div>
      <div class="profile-name"><?= sanitize($user['nombre'].' '.($user['apellido']??'')) ?></div>
      <div style="text-align:center;margin-bottom:6px;">
        <span style="background:var(--bg3);color:var(--gris3);font-size:10px;font-weight:800;padding:3px 10px;border-radius:12px;text-transform:uppercase;">
          Cliente
        </span>
      </div>
      <div class="profile-email"><?= sanitize($user['email']) ?></div>

      <!-- Stats rápidas -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:16px 0;padding:14px;background:var(--bg3);border-radius:var(--r);">
        <div style="text-align:center;">
          <div style="font-size:20px;font-weight:900;color:var(--amarillo);\"><?= $totalPedidos ?></div>
          <div style="font-size:10px;color:var(--gris3);">Pedidos</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:20px;font-weight:900;color:var(--amarillo);">
            <?= number_format((int)($user['puntos'] ?? 0)) ?>
          </div>
          <div style="font-size:10px;color:var(--gris3);">Puntos acumulados</div>
        </div>
      </div>

      <nav class="profile-nav">
        <a href="?tab=datos"     class="<?= $tab==='datos'    ?'active':'' ?>"><i class="fas fa-user-edit"></i>  Mis datos</a>
        <a href="?tab=seguridad" class="<?= $tab==='seguridad'?'active':'' ?>"><i class="fas fa-lock"></i>       Contraseña</a>
        <a href="mis-pedidos.php">                                                <i class="fas fa-history"></i>    Mis Pedidos</a>
        <?php if(isAdmin()): ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="admin/index.php" style="color:var(--amarillo);font-weight:800;">
          <i class="fas fa-tachometer-alt"></i> Panel Admin
        </a>
        <?php endif; ?>
        <div style="height:1px;background:var(--borde);margin:10px 0;"></div>
        <a href="logout.php" style="color:var(--rojo);"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
      </nav>
    </div>

    <!-- ── CONTENIDO ────────────────────────────────────────── -->
    <div>

      <!-- TAB: MIS DATOS -->
      <?php if ($tab === 'datos'): ?>
      <div class="profile-content">
        <h2 style="font-size:20px;font-weight:800;color:var(--blanco);margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--borde);display:flex;align-items:center;gap:8px;">
          <i class="fas fa-user-edit" style="color:var(--amarillo);"></i> Mis datos personales
        </h2>
        <form method="POST">
          <input type="hidden" name="action" value="update_perfil">

          <div class="form-row">
            <div class="form-group">
              <label>Puntos acumulados</label>
              <input type="text" value="<?= number_format((int)($user['puntos'] ?? 0)) ?> pts" disabled style="opacity:.5;cursor:not-allowed;">
            </div>

            <?php if(!empty($user['dni_ruc'])): ?>
            <div class="form-group">
              <label>Documento de identidad</label>
              <input type="text" value="<?= sanitize($user['tipo_documento'] ?? 'DNI') . ' - ' . sanitize($user['dni_ruc']) ?>" disabled style="opacity:.5;cursor:not-allowed;">
            </div>
            <?php endif; ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Nombre *</label>
              <input type="text" name="nombre" value="<?= sanitize($user['nombre']) ?>" required>
            </div>
            <div class="form-group">
              <label>Apellido</label>
              <input type="text" name="apellido" value="<?= sanitize($user['apellido']??'') ?>">
            </div>
          </div>

          <div class="form-group">
            <label>Correo electrónico</label>
            <input type="email" value="<?= sanitize($user['email']) ?>" disabled style="opacity:.5;cursor:not-allowed;">
          </div>

          <div class="form-group">
            <label>Celular</label>
            <input type="tel" name="celular" value="<?= sanitize($user['celular']??$user['telefono']??'') ?>" placeholder="987 654 321">
          </div>

          <?php if(!empty($user['fecha_nacimiento'])): ?>
          <div class="form-group">
            <label>Fecha de nacimiento</label>
            <input type="text" value="<?= date('d/m/Y', strtotime($user['fecha_nacimiento'])) ?>" disabled style="opacity:.5;cursor:not-allowed;">
          </div>
          <?php endif; ?>

          <div class="form-group">
            <label>Dirección de entrega</label>
            <input type="text" name="direccion" value="<?= sanitize($user['direccion_entrega']??$user['direccion']??'') ?>" placeholder="Av. Javier Prado 1234, Lima">
          </div>

          <button type="submit" class="btn-submit" style="max-width:220px;">
            <i class="fas fa-save"></i> Guardar cambios
          </button>
        </form>
      </div>

      <!-- TAB: CONTRASEÑA -->
      <?php elseif ($tab === 'seguridad'): ?>
      <div class="profile-content">
        <h2 style="font-size:20px;font-weight:800;color:var(--blanco);margin-bottom:20px;padding-bottom:12px;border-bottom:1px solid var(--borde);display:flex;align-items:center;gap:8px;">
          <i class="fas fa-lock" style="color:var(--amarillo);"></i> Cambiar contraseña
        </h2>
        <form method="POST" style="max-width:420px;">
          <input type="hidden" name="action" value="change_pass">
          <div class="form-group">
            <label>Contraseña actual</label>
            <div class="password-wrap">
              <input type="password" name="pass_actual" id="pa" required>
              <button type="button" class="toggle-password" onclick="togglePass('pa',this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Nueva contraseña</label>
            <div class="password-wrap">
              <input type="password" name="pass_nueva" id="pn" placeholder="Mínimo 6 caracteres" required>
              <button type="button" class="toggle-password" onclick="togglePass('pn',this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label>Repetir nueva contraseña</label>
            <div class="password-wrap">
              <input type="password" name="pass_nueva2" id="pn2" required>
              <button type="button" class="toggle-password" onclick="togglePass('pn2',this)"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <button type="submit" class="btn-submit" style="max-width:220px;">
            <i class="fas fa-key"></i> Cambiar contraseña
          </button>
        </form>
      </div>
      <?php endif; ?>

    </div><!-- /contenido -->
  </div><!-- /profile-layout -->
</div>

<script>
function togglePass(id,btn){const i=document.getElementById(id),p=i.type==='password';i.type=p?'text':'password';btn.innerHTML=p?'<i class="fas fa-eye-slash"></i>':'<i class="fas fa-eye"></i>';}
</script>
<?php include 'includes/footer.php'; ?>