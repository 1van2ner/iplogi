<?php
// Script para asignar cupones de cumpleaños a usuarios cuya fecha de nacimiento coincide con hoy.
// Diseñado para ejecutarse por cron diariamente.

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/funciones_cupones.php';

$pdo = getDB();

// ID del cupón de cumpleaños en la tabla `cupones` (ajusta si es otro)
$cuponCumpleId = 2;
// Días de validez del cupón desde la fecha de asignación
$diasValidez = 30;

echo "Asignando cupones de cumpleaños para " . date('Y-m-d') . "\n";

try {
    $sql = "SELECT id, nombre, apellido, email, fecha_nacimiento FROM usuarios WHERE fecha_nacimiento IS NOT NULL AND fecha_nacimiento != '0000-00-00'";
    $stmt = $pdo->query($sql);
    $count = 0; $assigned = 0;
    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $count++;
        $fn = $u['fecha_nacimiento'];
        if (!$fn) continue;
        // comparar solo mes y día
        $md_user = date('m-d', strtotime($fn));
        $md_today = date('m-d');
        if ($md_user !== $md_today) continue;

        // Intentar asignar (la función evita duplicados por año)
        $ok = asignarCuponCumpleAnual($pdo, $u['id'], $cuponCumpleId, $diasValidez);
        if ($ok) {
            $assigned++;
            echo "  - Cupón asignado a usuario {$u['id']} ({$u['email']})\n";
        } else {
            echo "  - Ya tiene cupón este año: usuario {$u['id']} ({$u['email']})\n";
        }
    }

    echo "Proceso completado. Usuarios revisados: $count. Cupones asignados: $assigned.\n";
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(2);
}

?>
