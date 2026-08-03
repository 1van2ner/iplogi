<?php
require_once 'includes/config.php';

$token = $_GET['token'] ?? '';
$status = 'error';
$title = 'Enlace inválido';
$message = 'El enlace de verificación no es válido o ya no está disponible.';

if ($token) {
    $pdo = getDB();

    $stmt = $pdo->prepare("
        SELECT id, nombre, verify_expires, is_verified
        FROM usuarios
        WHERE verify_token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        if ((int)$user['is_verified'] === 1) {
            $status = 'info';
            $title = 'Cuenta ya verificada';
            $message = 'Tu cuenta ya estaba verificada. Puedes iniciar sesión normalmente.';
        } elseif (!empty($user['verify_expires']) && strtotime($user['verify_expires']) < time()) {
            $status = 'expired';
            $title = 'Enlace expirado';
            $message = 'El enlace de verificación expiró. Solicita uno nuevo.';
        } else {
            $upd = $pdo->prepare("
                UPDATE usuarios
                SET is_verified = 1,
                    verify_token = NULL,
                    verify_expires = NULL
                WHERE id = ?
            ");
            $upd->execute([$user['id']]);

            $status = 'success';
            $title = '¡Cuenta verificada!';
            $message = 'Tu correo fue confirmado correctamente. Ya puedes iniciar sesión.';
        }
    }
}

$pageTitle = 'Verificación de Cuenta';
include 'includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card" style="max-width:560px;">
    <div class="auth-logo">
      <img src="/assets/img/logop.jpg" alt="IP Tecnología" class="logo-img">
      <h2><?= sanitize($title) ?></h2>
      <p><?= sanitize($message) ?></p>
    </div>

    <?php if ($status === 'success'): ?>
      <div class="alert alert-success" style="margin-bottom:18px;">
        <i class="fas fa-check-circle"></i> Tu cuenta ya está activa.
      </div>
      <a href="<?= SITE_URL ?>/login.php" class="btn-submit" style="display:block;text-align:center;">
        <i class="fas fa-sign-in-alt"></i> Ir a iniciar sesión
      </a>

    <?php elseif ($status === 'expired'): ?>
      <div class="alert alert-error" style="margin-bottom:18px;">
        <i class="fas fa-clock"></i> El enlace expiró.
      </div>
      <a href="<?= SITE_URL ?>/login.php" class="btn-submit" style="display:block;text-align:center;">
        <i class="fas fa-arrow-left"></i> Volver al login
      </a>

    <?php elseif ($status === 'info'): ?>
      <div class="alert" style="margin-bottom:18px;">
        <i class="fas fa-info-circle"></i> No necesitas verificarla otra vez.
      </div>
      <a href="<?= SITE_URL ?>/login.php" class="btn-submit" style="display:block;text-align:center;">
        <i class="fas fa-sign-in-alt"></i> Iniciar sesión
      </a>

    <?php else: ?>
      <div class="alert alert-error" style="margin-bottom:18px;">
        <i class="fas fa-times-circle"></i> No pudimos validar el enlace.
      </div>
      <a href="<?= SITE_URL ?>/registro.php" class="btn-submit" style="display:block;text-align:center;">
        <i class="fas fa-user-plus"></i> Crear cuenta
      </a>
    <?php endif; ?>

    <div class="auth-footer" style="margin-top:18px;">
      <a href="<?= SITE_URL ?>/index.php">Volver al inicio</a>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>