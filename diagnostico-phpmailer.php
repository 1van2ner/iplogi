<?php
/**
 * 🔍 DIAGNÓSTICO DE PHPMAILER
 * Script para identificar problemas de configuración y conectividad
 */

require_once 'includes/config.php';

echo '<style>
body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
.box { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #333; }
.ok { border-left-color: #4caf50; }
.error { border-left-color: #f44336; }
.warning { border-left-color: #ff9800; }
h1 { color: #333; }
h2 { color: #666; font-size: 16px; margin-top: 0; }
code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
.code-block { background: #f0f0f0; padding: 12px; border-radius: 4px; margin: 10px 0; overflow-x: auto; }
.row { display: flex; gap: 20px; }
.col { flex: 1; }
</style>';

echo '<h1>🔍 Diagnóstico de PHPMailer</h1>';

// ========================================
// 1. VERIFICAR CONSTANTES
// ========================================
echo '<div class="box ' . (defined('MAIL_HOST') ? 'ok' : 'error') . '">';
echo '<h2>✓ 1. Constantes de Configuración</h2>';

$constants = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USER', 'MAIL_PASS', 'MAIL_FROM', 'MAIL_NAME'];
$allDefined = true;

echo '<table style="width:100%; border-collapse: collapse;">';
foreach ($constants as $const) {
    $defined = defined($const);
    $allDefined = $allDefined && $defined;
    $value = $defined ? (strpos($const, 'PASS') !== false ? '***' : constant($const)) : '❌ NO DEFINIDA';
    echo '<tr style="border-bottom: 1px solid #eee;">';
    echo '<td style="padding: 8px;">' . $const . '</td>';
    echo '<td style="padding: 8px; color: ' . ($defined ? '#4caf50' : '#f44336') . ';">' . $value . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '</div>';

// ========================================
// 2. VERIFICAR PHPMAILER AUTOLOAD
// ========================================
echo '<div class="box ' . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'ok' : 'error') . '">';
echo '<h2>✓ 2. PHPMailer Autoload</h2>';

if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo '✅ PHPMailer cargado correctamente<br>';
    echo 'Versión: ' . (defined('PHPMailer\PHPMailer\PHPMailer::VERSION') ? PHPMailer\PHPMailer\PHPMailer::VERSION : 'Desconocida');
} else {
    echo '❌ PHPMailer NO está cargado<br>';
    echo 'Verifica que vendor/autoload.php existe y está en includes/config.php';
}

echo '</div>';

// ========================================
// 3. VERIFICAR ARCHIVOS
// ========================================
echo '<div class="box ' . (file_exists('vendor/autoload.php') ? 'ok' : 'error') . '">';
echo '<h2>✓ 3. Archivos Necesarios</h2>';

$files = [
    'vendor/autoload.php' => 'Autoload de Composer',
    'vendor/phpmailer/phpmailer/src/PHPMailer.php' => 'Clase Principal PHPMailer',
    'vendor/phpmailer/phpmailer/src/SMTP.php' => 'Clase SMTP',
    'vendor/phpmailer/phpmailer/src/Exception.php' => 'Clase Exception',
];

foreach ($files as $file => $description) {
    $exists = file_exists($file);
    echo '<div style="padding: 8px; color: ' . ($exists ? '#4caf50' : '#f44336') . ';">';
    echo ($exists ? '✅' : '❌') . ' ' . $description . ' <code>' . $file . '</code>';
    echo '</div>';
}

echo '</div>';

// ========================================
// 4. PRUEBA DE CONEXIÓN SMTP
// ========================================
echo '<div class="box">';
echo '<h2>⚡ 4. Prueba de Conexión SMTP</h2>';

if (!$allDefined) {
    echo '<div style="color: #ff9800;">⚠️ No se puede probar la conexión porque faltan constantes</div>';
} else {
    try {
        echo 'Conectando a ' . MAIL_HOST . ':' . MAIL_PORT . '...<br>';
        $sock = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 5);
        
        if ($sock) {
            echo '✅ Conexión TCP establecida<br>';
            fclose($sock);
            echo '📧 El servidor SMTP está disponible';
        } else {
            echo '❌ No se puede conectar al SMTP<br>';
            echo 'Error: ' . $errstr . ' (' . $errno . ')';
        }
    } catch (Exception $e) {
        echo '❌ Error al conectar: ' . $e->getMessage();
    }
}

echo '</div>';

// ========================================
// 5. RECOMENDACIONES
// ========================================
echo '<div class="box warning">';
echo '<h2>💡 Recomendaciones</h2>';

echo '<p><strong>Si no funciona, verifica:</strong></p>';
echo '<ol>';
echo '<li><strong>Credenciales SMTP:</strong> Usuario y contraseña correctos en Banahosting</li>';
echo '<li><strong>Puerto:</strong> 465 es correcto para SMTPS (SSL/TLS)</li>';
echo '<li><strong>Firewall:</strong> El servidor permite conexiones salientes al puerto 465</li>';
echo '<li><strong>Límites de hosting:</strong> Banahosting limita correos por hora/día</li>';
echo '<li><strong>Logs:</strong> Revisa ' . ini_get('error_log') . '</li>';
echo '</ol>';

echo '<div class="code-block">';
echo '<strong>Para ver errores en desarrollo, abre:</strong><br>';
echo 'C:\xampp\php\logs\php_error_log';
echo '</div>';

echo '</div>';

// ========================================
// 6. INFO DEL SERVIDOR
// ========================================
echo '<div class="box">';
echo '<h2>ℹ️ 5. Información del Servidor</h2>';

echo '<table style="width:100%; border-collapse: collapse;">';
$info = [
    'PHP Version' => phpversion(),
    'OpenSSL' => extension_loaded('openssl') ? '✅ Instalada' : '❌ NO instalada',
    'cURL' => extension_loaded('curl') ? '✅ Instalada' : '❌ NO instalada',
    'Sockets' => extension_loaded('sockets') ? '✅ Instalada' : '❌ NO instalada',
    'APP_DEBUG' => defined('APP_DEBUG') && APP_DEBUG ? '✅ ACTIVADO' : '❌ Desactivado',
];

foreach ($info as $key => $value) {
    echo '<tr style="border-bottom: 1px solid #eee;">';
    echo '<td style="padding: 8px;"><strong>' . $key . ':</strong></td>';
    echo '<td style="padding: 8px;">' . $value . '</td>';
    echo '</tr>';
}
echo '</table>';

echo '</div>';

?>
