<?php
// Forzar entorno local
$_SERVER['HTTP_HOST'] = 'localhost';

require_once 'includes/config.php';

$pdo = getDB();

try {
    $stmt = $pdo->prepare("UPDATE cupones SET tipo_descuento=?, descuento=?, compra_minima=? WHERE id=?");
    $stmt->execute(['monto', 10, 100, 1]);
    echo "✓ Cupón de bienvenida actualizado correctamente: S/10 con compra mínima de S/100\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
