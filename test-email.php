<?php
require_once 'includes/config.php';
require_once 'includes/email_verification.php';

echo '<pre style="background:#f5f5f5;padding:20px;border-radius:8px;font-family:monospace;">';
echo '<h2>TEST: Envío de Email de Verificación</h2>';
echo str_repeat('=', 60) . "\n\n";

// Datos de prueba
$testEmail = 'test@example.com';  // CAMBIA ESTO A TU EMAIL FOR REAL
$testToken = bin2hex(random_bytes(32));

echo "📧 Email de prueba: " . $testEmail . "\n";
echo "🔑 Token: " . substr($testToken, 0, 20) . "...\n\n";

// Intentar enviar
echo "⏳ Intentando enviar email...\n";
$resultado = sendVerificationEmail($testEmail, 'Usuario Test', $testToken);

echo "\n" . str_repeat('=', 60) . "\n";
echo "✓ Resultado: " . ($resultado ? '✅ ENVIADO' : '❌ NO ENVIADO') . "\n";
echo str_repeat('=', 60) . "\n";

// Mostrar logs
echo "\n📋 REVISAR LOS LOGS EN:\n";
echo "C:\\xampp\\php\\logs\\php_error_log\n";
echo "O en: " . ini_get('error_log') . "\n";

?>
</pre>
