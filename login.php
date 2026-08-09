<?php
require_once 'includes/config.php';
require_once 'includes/funciones_cupones.php';
if (isLoggedIn()) { header('Location: ' . SITE_URL . '/index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if (!$email || !$pass) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $pdo = getDB();
        $s = $pdo->prepare("SELECT * FROM usuarios WHERE email=? AND activo=1");
        $s->execute([$email]);
        $user = $s->fetch();
        if ($user && password_verify($pass, $user['password'])) {

            $verificado = !array_key_exists('is_verified', $user) || (int)$user['is_verified'] === 1;

            if (!$verificado) {
                $error = 'Debes verificar tu correo antes de iniciar sesión. Revisa tu bandeja de entrada.';
            } else {
                $_SESSION['usuario_id']  = $user['id'];
                $_SESSION['nombre']      = $user['nombre'];
                $_SESSION['email']       = $user['email'];
                $_SESSION['rol']         = $user['rol'];

                $stmtUser = $pdo->prepare("SELECT fecha_nacimiento FROM usuarios WHERE id = ?");
                $stmtUser->execute([$_SESSION['usuario_id']]);
                $userData = $stmtUser->fetch();

                if ($userData && !empty($userData['fecha_nacimiento']) && $userData['fecha_nacimiento'] !== '0000-00-00') {
                    $hoy = date('m-d');
                    $cumpleUsuario = date('m-d', strtotime($userData['fecha_nacimiento']));

                    if ($hoy === $cumpleUsuario) {
                        $cupon_cumple_id = 2;

                        if (asignarCuponAutomatico($pdo, $_SESSION['usuario_id'], $cupon_cumple_id, 7)) {
                            $_SESSION['flash_message'] = "¡Feliz cumpleaños! Te hemos regalado un cupón de descuento especial en tu cuenta.";
                            $_SESSION['flash_type'] = "success";
                        }
                    }
                }

                $pdo->prepare("UPDATE carrito SET usuario_id=?, session_id=NULL WHERE session_id=?")
                    ->execute([$user['id'], session_id()]);

                $_SESSION['flash_message'] = '¡Bienvenido de vuelta, ' . explode(' ', $user['nombre'])[0] . '!';
                $_SESSION['flash_type']    = 'success';

                $redirect = $_GET['redirect'] ?? SITE_URL . '/index.php';
                header('Location: ' . $redirect);
                exit;
            }

        } else {
            $error = 'Email o contraseña incorrectos.';
        }
    }
}
$pageTitle = 'Iniciar Sesión';
include 'includes/header.php';
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <img src="/assets/img/logo.ico" alt="IP Tecnología Perú" class="logo-img">
      <h2>Iniciar Sesión</h2>
      <p>Accede a tu cuenta para gestionar tus pedidos</p>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= $error ?></div>
    <?php endif; ?>
    <form method="POST" onsubmit="return validateLogin()">
      <div class="form-group">
        <label>Correo electrónico</label>
        <input type="email" name="email" id="email" value="<?= sanitize($_POST['email'] ?? '') ?>" placeholder="tucorreo@ejemplo.com" required>
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <div class="password-wrap">
          <input type="password" name="password" id="password" placeholder="Tu contraseña" required>
          <button type="button" onclick="togglePass('password',this)"
            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:16px;padding:4px;z-index:10;width:auto;min-height:unset;">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;font-size:13px;">
        <label style="display:flex;align-items:center;gap:6px;color:var(--gray-lighter);cursor:pointer;font-weight:400;">
          <input type="checkbox" name="remember" style="width:auto;accent-color:var(--primary);"> Recordarme
        </label>
        <a href="#" style="color:var(--primary);">¿Olvidaste tu contraseña?</a>
      </div>
      <button type="submit" class="btn-submit"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
    </form>
    <div class="auth-footer">
      ¿No tienes cuenta? <a href="<?= SITE_URL ?>/registro.php">Crear cuenta gratis</a>
    </div>
  </div>
</div>
<script>
function togglePass(id, btn) {
  const inp = document.getElementById(id);
  const isPass = inp.type === 'password';
  inp.type = isPass ? 'text' : 'password';
  btn.innerHTML = isPass ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
}
function validateLogin() {
  const e = document.getElementById('email').value;
  const p = document.getElementById('password').value;
  if (!e || !p) { alert('Completa todos los campos'); return false; }
  return true;
}
</script>
<?php include 'includes/footer.php'; ?>