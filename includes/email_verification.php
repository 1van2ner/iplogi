<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ⚠️ El autoload ya está en config.php, pero lo dejamos aquí para seguridad
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// ✓ Validar que las constantes de correo estén definidas
if (!defined('MAIL_HOST') || !defined('MAIL_USER') || !defined('MAIL_PASS') || !defined('MAIL_PORT')) {
    error_log('❌ ERROR: Constantes de correo no definidas. Verifica includes/config.php');
    die('Error de configuración de correo. Contacta al administrador.');
}

// Correo(s) que deben enterarse cuando alguien se registra como Proyectista.
// ⚠️ EDITAR: reemplazar por el correo real de Sheerley.
if (!defined('NOTIFY_EMAIL_PROYECTISTA')) {
    define('NOTIFY_EMAIL_PROYECTISTA', 'scisneros@iptecnologiaperu.com');
}

if (!function_exists('sendNotificacionRolEspecial')) {
    function sendNotificacionRolEspecial($datosUsuario, $rol) {
        $etiquetas = ['tecnico' => 'Técnico', 'proyectista' => 'Proyectista', 'distribuidor' => 'Distribuidor'];
        $etiqueta  = $etiquetas[$rol] ?? $rol;

        $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#222;">'
              . '<h3>Nuevo registro: ' . htmlspecialchars($etiqueta) . '</h3>'
              . '<p>Se registró un usuario que la encuesta clasificó como <strong>' . htmlspecialchars($etiqueta) . '</strong>. '
              . 'Revísalo en el panel admin y marca el check de verificación cuando confirmes que corresponde.</p>'
              . '<ul>'
              . '<li><strong>Nombre:</strong> ' . htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido']) . '</li>'
              . '<li><strong>Correo:</strong> ' . htmlspecialchars($datosUsuario['email']) . '</li>'
              . '<li><strong>Celular:</strong> ' . htmlspecialchars($datosUsuario['celular']) . '</li>'
              . '<li><strong>Documento:</strong> ' . htmlspecialchars($datosUsuario['tipo_documento'] . ' ' . $datosUsuario['dni_ruc']) . '</li>'
              . '</ul>'
              . '<p><a href="' . SITE_URL . '/admin/index.php?tab=usuarios&quser=' . urlencode($datosUsuario['email']) . '">Ver en el panel admin</a></p>'
              . '</div>';

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // TLS en puerto 465
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            // ✓ Debugging en desarrollo
            if (APP_DEBUG) {
                $mail->SMTPDebug = 2;  // 0=sin debug, 2=debug client y server
            }

            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            $mail->addAddress(NOTIFY_EMAIL_PROYECTISTA);
            $mail->isHTML(true);
            $mail->Subject = 'Nuevo registro: ' . $etiqueta . ' — ' . $datosUsuario['nombre'];
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            $errorMsg = 'PHPMailer Error: ' . $e->getMessage();
            error_log('❌ ' . $errorMsg);
            if (APP_DEBUG) {
                error_log('📧 SMTP Host: ' . MAIL_HOST);
                error_log('📧 SMTP Port: ' . MAIL_PORT);
                error_log('📧 SMTP User: ' . MAIL_USER);
            }
            return false;
        }
    }
}

if (!function_exists('sendVerificationEmail')) {
    function sendVerificationEmail($toEmail, $toName, $token) {
        $verifyLink = SITE_URL . '/verify.php?token=' . urlencode($token);

        $htmlBody = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#CEFF04;font-family:Arial,Helvetica,sans-serif;">

  <table width="100%" cellpadding="0" cellspacing="0" style="background:#CEFF04;padding:40px 16px;">
    <tr><td align="center">
      <table width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;">

        <!-- HEADER CON LOGO -->
        <tr>
          <td style="background:#CEFF04;border-radius:16px 16px 0 0;padding:28px 24px;text-align:center;">
            <img src="https://iptecnologiaperu.com/assets/img/logo.ico"
                 alt="IP Tecnología Perú"
                 width="140"
                 style="display:block;margin:0 auto;width:140px;height:auto;">
          </td>
        </tr>

        <!-- BODY -->
        <tr>
          <td style="background:#ffffff;padding:40px 36px;">

            <!-- Ícono -->
            <div style="text-align:center;margin-bottom:28px;">
              <div style="display:inline-block;background:#CEFF04;border-radius:50%;width:64px;height:64px;line-height:64px;font-size:28px;text-align:center;">✉</div>
            </div>

            <h2 style="color:#111;font-size:22px;margin:0 0 8px;text-align:center;">Verifica tu correo</h2>
            <p style="color:#555;font-size:14px;text-align:center;margin:0 0 32px;">Hola <strong style="color:#000;">' . htmlspecialchars($toName) . '</strong>, gracias por registrarte. Un solo clic y tu cuenta estará lista.</p>

            <!-- Botón -->
            <div style="text-align:center;margin-bottom:32px;">
              <a href="' . $verifyLink . '" style="display:inline-block;background:#CEFF04;color:#000;text-decoration:none;padding:16px 40px;border-radius:999px;font-weight:900;font-size:15px;letter-spacing:0.5px;border:2px solid #000;">
                ✓ &nbsp;VERIFICAR MI CUENTA
              </a>
            </div>

            <!-- Divider -->
            <div style="border-top:1px solid #e0e0e0;margin:28px 0;"></div>

            <!-- Avisos -->
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:10px;padding:16px 20px;">
                  <p style="margin:0;color:#777;font-size:12px;line-height:1.8;">
                    ⏱ &nbsp;Este enlace <strong style="color:#333;">expira en 24 horas</strong>.<br>
                    🔒 &nbsp;Si no creaste una cuenta, ignora este mensaje.<br>
                    ❌ &nbsp;No respondas este correo, es de envío automático.
                  </p>
                </td>
              </tr>
            </table>

          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="background:#CEFF04;border-radius:0 0 16px 16px;padding:20px 24px;text-align:center;border-top:2px solid #000;">
            <p style="margin:0 0 4px;color:#000;font-size:11px;font-weight:700;">© 2026 IP Tecnología Perú — Todos los derechos reservados</p>
            <p style="margin:0;color:#333;font-size:11px;">Lima, Perú &nbsp;·&nbsp; iptecnologiaperu.com</p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>

</body>
</html>';

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USER;
            $mail->Password   = MAIL_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // TLS en puerto 465
            $mail->Port       = MAIL_PORT;
            $mail->CharSet    = 'UTF-8';

            // ✓ Debugging en desarrollo
            if (APP_DEBUG) {
                $mail->SMTPDebug = 2;  // 0=sin debug, 2=debug client y server
            }

            $mail->setFrom(MAIL_FROM, MAIL_NAME);
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = 'Verifica tu cuenta';
            $mail->Body    = $htmlBody;
            $mail->AltBody = 'Hola ' . $toName . ', verifica tu cuenta aquí: ' . $verifyLink;

            $mail->send();
            return true;
        } catch (Exception $e) {
            $errorMsg = 'PHPMailer Error: ' . $e->getMessage();
            error_log('❌ ' . $errorMsg);
            if (APP_DEBUG) {
                error_log('📧 SMTP Host: ' . MAIL_HOST);
                error_log('📧 SMTP Port: ' . MAIL_PORT);
                error_log('📧 To Email: ' . $toEmail);
            }
            return false;
        }
    }
}