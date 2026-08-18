<?php
function asignarCuponAutomatico($pdo, $usuario_id, $cupon_id, $dias_validez = 30) {
    // 1. Verificar si ya se le asignó este cupón
    $stmt = $pdo->prepare("SELECT id FROM usuario_cupones WHERE usuario_id = ? AND cupon_id = ?");
    $stmt->execute([$usuario_id, $cupon_id]);
    if ($stmt->fetch()) {
        return false;
    }

    // 2. Generar código único
    $codigo_personal = "BIENVENIDA-" . strtoupper(substr(uniqid(), -6));

    // 3. Fecha de expiración
    $fecha_expiracion = date('Y-m-d', strtotime("+$dias_validez days"));

    // 4. Insertar en la base de datos
    $insert = $pdo->prepare("INSERT INTO usuario_cupones (usuario_id, cupon_id, codigo_personal, fecha_expiracion, usado) VALUES (?, ?, ?, ?, 0)");
    return $insert->execute([$usuario_id, $cupon_id, $codigo_personal, $fecha_expiracion]);
}

/**
 * Asigna un cupón de cumpleaños anual al usuario.
 * Solo asigna una vez por año (busca por YEAR(fecha_expiracion) = YEAR(CURDATE())).
 * Genera un código tipo CUMPLE-<YYYY>-XXXXXX.
 */
function asignarCuponCumpleAnual($pdo, $usuario_id, $cupon_id, $dias_validez = 30) {
    // Evitar duplicados en el mismo año
    $stmt = $pdo->prepare("SELECT id FROM usuario_cupones WHERE usuario_id = ? AND cupon_id = ? AND YEAR(fecha_expiracion) = YEAR(CURDATE())");
    $stmt->execute([$usuario_id, $cupon_id]);
    if ($stmt->fetch()) {
        return false;
    }

    $codigo_personal = "CUMPLE-" . date('Y') . "-" . strtoupper(substr(uniqid(), -6));
    $fecha_expiracion = date('Y-m-d', strtotime("+$dias_validez days"));

    $insert = $pdo->prepare("INSERT INTO usuario_cupones (usuario_id, cupon_id, codigo_personal, fecha_expiracion, usado) VALUES (?, ?, ?, ?, 0)");
    return $insert->execute([$usuario_id, $cupon_id, $codigo_personal, $fecha_expiracion]);
}
?>