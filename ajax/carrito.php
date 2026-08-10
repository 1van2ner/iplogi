<?php
require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: application/json');

$pdo    = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Asegurar que exista la tabla control_puntos (para auditoría de movimientos)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS control_puntos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        producto_id INT NULL,
        puntos INT NOT NULL,
        tipo_movimiento VARCHAR(32) NOT NULL,
        descripcion TEXT NULL,
        creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Si falla, continuar y dejar que las inserciones lancen el error normal
}

// ── Ensure carrito table has es_canje_puntos column ──
try {
    $col = $pdo->query("SHOW COLUMNS FROM carrito LIKE 'es_canje_puntos'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec('ALTER TABLE carrito ADD COLUMN es_canje_puntos TINYINT DEFAULT 0');
    }
} catch (Exception $e) {
    // Tabla no existe o error, continuar de todas formas
}

// ── Helpers ──────────────────────────────────────────────────
function getWhere() {
    if (isLoggedIn()) {
        return ['campo' => 'usuario_id', 'valor' => $_SESSION['usuario_id']];
    }
    return ['campo' => 'session_id', 'valor' => session_id()];
}

function getCount($pdo) {
    $w = getWhere();
    $s = $pdo->prepare("SELECT COALESCE(SUM(cantidad),0) FROM carrito WHERE {$w['campo']} = ?");
    $s->execute([$w['valor']]);
    return (int)$s->fetchColumn();
}

switch ($action) {

    // ── AGREGAR ───────────────────────────────────────────────
    case 'agregar':
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));
        $usarPuntos = !empty($_POST['usar_puntos']) ? 1 : 0;

        if (!$productoId) {
            echo json_encode(['success' => false, 'message' => 'Producto inválido']);
            exit;
        }

        $p = $pdo->prepare("SELECT id, nombre, stock, canje_puntos FROM productos WHERE id = ? AND activo = 1");
        $p->execute([$productoId]);
        $producto = $p->fetch();

        if (!$producto) {
            echo json_encode(['success' => false, 'message' => 'Producto no disponible']);
            exit;
        }
        if ($producto['stock'] < 1) {
            echo json_encode(['success' => false, 'message' => 'Sin stock disponible']);
            exit;
        }

        // Validar y descontar puntos si es canje
        if ($usarPuntos) {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para canjear por puntos']);
                exit;
            }
            if (empty($producto['canje_puntos'])) {
                echo json_encode(['success' => false, 'message' => 'Este producto no se puede canjear por puntos']);
                exit;
            }

            // Revisar si ya existe en carrito (uso de puntos) para deducir sólo la diferencia
            $w = getWhere();
            $check = $pdo->prepare("SELECT id, cantidad, es_canje_puntos FROM carrito WHERE producto_id = ? AND {$w['campo']} = ? AND es_canje_puntos = 1");
            $check->execute([$productoId, $w['valor']]);
            $existing = $check->fetch();

            $puntosPorUnidad = (int)$producto['canje_puntos'];
            $puntosNecesariosTotal = $puntosPorUnidad * $cantidad;
            $puntosADescontar = $puntosNecesariosTotal;
            if ($existing) {
                // Si ya estaba canjeado con puntos, solo descontar la diferencia (si aumenta cantidad)
                $existQty = (int)$existing['cantidad'];
                if ($cantidad <= $existQty) {
                    $puntosADescontar = 0;
                } else {
                    $puntosADescontar = $puntosPorUnidad * ($cantidad - $existQty);
                }
            }

            if ($puntosADescontar > 0) {
                $pdo->beginTransaction();
                try {
                    $userStmt = $pdo->prepare("SELECT puntos FROM usuarios WHERE id = ? FOR UPDATE");
                    $userStmt->execute([$_SESSION['usuario_id']]);
                    $userRow = $userStmt->fetch();
                    $userPoints = (int)($userRow['puntos'] ?? 0);

                    if ($userPoints < $puntosADescontar) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'No tienes suficientes puntos. Necesitas ' . $puntosADescontar . ' puntos adicionales.']);
                        exit;
                    }

                    $upd = $pdo->prepare("UPDATE usuarios SET puntos = puntos - ? WHERE id = ?");
                    $upd->execute([$puntosADescontar, $_SESSION['usuario_id']]);
                    // Registrar movimiento de puntos (CANJE)
                    $ins = $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, ?, ?, 'CANJE', ?, NOW())");
                    $ins->execute([$_SESSION['usuario_id'], $productoId, -$puntosADescontar, 'Canje en carrito']);
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al procesar puntos: ' . $e->getMessage()]);
                    exit;
                }
            }
        }

        $w = getWhere();

        $check = $pdo->prepare("SELECT id, cantidad, es_canje_puntos FROM carrito WHERE producto_id = ? AND {$w['campo']} = ? AND es_canje_puntos = ?");
        $check->execute([$productoId, $w['valor'], $usarPuntos]);
        $existing = $check->fetch();

        try {
            if ($existing) {
                $nueva = min($existing['cantidad'] + $cantidad, $producto['stock']);
                $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$nueva, $existing['id']]);
            } else {
                if (isLoggedIn()) {
                    $pdo->prepare("INSERT INTO carrito (usuario_id, session_id, producto_id, cantidad, es_canje_puntos) VALUES (?, NULL, ?, ?, ?)")
                        ->execute([$_SESSION['usuario_id'], $productoId, $cantidad, $usarPuntos]);
                } else {
                    $pdo->prepare("INSERT INTO carrito (usuario_id, session_id, producto_id, cantidad, es_canje_puntos) VALUES (NULL, ?, ?, ?, ?)")
                        ->execute([session_id(), $productoId, $cantidad, $usarPuntos]);
                }
            }
        } catch (Exception $e) {
            // Si falló insertar/actualizar, intentar revertir puntos descontados (si aplica)
            if ($usarPuntos && !empty($_SESSION['usuario_id']) && isset($puntosADescontar) && $puntosADescontar > 0) {
                try {
                    $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$puntosADescontar, $_SESSION['usuario_id']]);
                } catch (Exception $ee) {
                    // No podemos hacer mucho si el reintegro falla
                }
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => '¡Producto agregado al carrito!', 'cart_count' => getCount($pdo)]);
        break;

    // ── ACTUALIZAR CANTIDAD ───────────────────────────────────
    case 'actualizar':
        $carritoId = (int)($_POST['carrito_id'] ?? 0);
        $cantidad  = max(1, (int)($_POST['cantidad'] ?? 1));

        if (!$carritoId) {
            echo json_encode(['success' => false, 'message' => 'Item inválido']);
            exit;
        }

        $w = getWhere();
        $row = $pdo->prepare("SELECT c.id, c.producto_id, c.cantidad, c.es_canje_puntos, p.stock FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.id = ? AND c.{$w['campo']} = ?");
        $row->execute([$carritoId, $w['valor']]);
        $item = $row->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item no encontrado']);
            exit;
        }

        $cantidad = min($cantidad, $item['stock']);

        // Si este item es canje por puntos, ajustar puntos del usuario según la diferencia
        $isCanje = (int)($item['es_canje_puntos'] ?? 0);
        if ($isCanje) {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Operación no permitida']);
                exit;
            }
            // obtener canje_puntos por unidad
            $p = $pdo->prepare("SELECT p.canje_puntos FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.id = ? AND c.{$w['campo']} = ?");
            $p->execute([$carritoId, $w['valor']]);
            $prod = $p->fetch();
            $puntosPorUnidad = (int)($prod['canje_puntos'] ?? 0);

            // cantidad actual en carrito
            $q = $pdo->prepare("SELECT cantidad FROM carrito WHERE id = ?");
            $q->execute([$carritoId]);
            $old = (int)($q->fetchColumn() ?? 0);

            if ($cantidad > $old) {
                $delta = $cantidad - $old;
                $puntosADescontar = $delta * $puntosPorUnidad;
                $pdo->beginTransaction();
                try {
                    $userStmt = $pdo->prepare("SELECT puntos FROM usuarios WHERE id = ? FOR UPDATE");
                    $userStmt->execute([$_SESSION['usuario_id']]);
                    $userPoints = (int)($userStmt->fetchColumn() ?? 0);
                    if ($userPoints < $puntosADescontar) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'No tienes suficientes puntos para aumentar la cantidad']);
                        exit;
                    }
                    $pdo->prepare("UPDATE usuarios SET puntos = puntos - ? WHERE id = ?")->execute([$puntosADescontar, $_SESSION['usuario_id']]);
                    $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, ?, ?, 'CANJE', ?, NOW())")->execute([$_SESSION['usuario_id'], $item['producto_id'] ?? null, -$puntosADescontar, 'Canje al aumentar cantidad']);
                    $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$cantidad, $carritoId]);
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar puntos: ' . $e->getMessage()]);
                    exit;
                }
            } elseif ($cantidad < $old) {
                // devolver puntos por la reducción
                $delta = $old - $cantidad;
                $puntosADevolver = $delta * $puntosPorUnidad;
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$puntosADevolver, $_SESSION['usuario_id']]);
                    $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, ?, ?, 'REINTEGRO', ?, NOW())")->execute([$_SESSION['usuario_id'], $item['producto_id'] ?? null, $puntosADevolver, 'Reintegro al reducir cantidad']);
                    $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$cantidad, $carritoId]);
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al devolver puntos: ' . $e->getMessage()]);
                    exit;
                }
            } else {
                // misma cantidad
                $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$cantidad, $carritoId]);
            }
        } else {
            $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$cantidad, $carritoId]);
        }

        echo json_encode(['success' => true, 'cart_count' => getCount($pdo)]);
        break;

    // ── ELIMINAR ITEM ─────────────────────────────────────────
    case 'eliminar':
        $carritoId = (int)($_POST['carrito_id'] ?? 0);
        if (!$carritoId) {
            echo json_encode(['success' => false, 'message' => 'Item inválido']);
            exit;
        }

        $w = getWhere();
        // Si el item era canje por puntos, devolver puntos al usuario
        $row = $pdo->prepare("SELECT c.cantidad, c.es_canje_puntos, p.canje_puntos FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.id = ? AND c.{$w['campo']} = ?");
        $row->execute([$carritoId, $w['valor']]);
        $r = $row->fetch();
        if ($r && (int)$r['es_canje_puntos'] === 1) {
            if (isLoggedIn()) {
                $puntos = (int)$r['cantidad'] * (int)($r['canje_puntos'] ?? 0);
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$puntos, $_SESSION['usuario_id']]);
                    $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, NULL, ?, 'REINTEGRO', ?, NOW())")->execute([$_SESSION['usuario_id'], $puntos, 'Reintegro por eliminación de carrito']);
                    $pdo->prepare("DELETE FROM carrito WHERE id = ? AND {$w['campo']} = ?")->execute([$carritoId, $w['valor']]);
                    $pdo->commit();
                    echo json_encode(['success' => true, 'cart_count' => getCount($pdo)]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al devolver puntos: ' . $e->getMessage()]);
                    exit;
                }
            }
        }

        $pdo->prepare("DELETE FROM carrito WHERE id = ? AND {$w['campo']} = ?")->execute([$carritoId, $w['valor']]);

        echo json_encode(['success' => true, 'cart_count' => getCount($pdo)]);
        break;

    // ── VACIAR CARRITO ────────────────────────────────────────
    case 'vaciar':
        $w = getWhere();
        // Devolver puntos de todos los items canjeados
        if (isLoggedIn()) {
            $sum = $pdo->prepare("SELECT COALESCE(SUM(c.cantidad * p.canje_puntos),0) FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.{$w['campo']} = ? AND c.es_canje_puntos = 1");
            $sum->execute([$w['valor']]);
            $puntos = (int)$sum->fetchColumn();
            if ($puntos > 0) {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE usuarios SET puntos = puntos + ? WHERE id = ?")->execute([$puntos, $_SESSION['usuario_id']]);
                    $pdo->prepare("INSERT INTO control_puntos (usuario_id, producto_id, puntos, tipo_movimiento, descripcion, creado_en) VALUES (?, NULL, ?, 'REINTEGRO', ?, NOW())")->execute([$_SESSION['usuario_id'], $puntos, 'Reintegro por vaciar carrito']);
                    $pdo->prepare("DELETE FROM carrito WHERE {$w['campo']} = ?")->execute([$w['valor']]);
                    $pdo->commit();
                    echo json_encode(['success' => true, 'cart_count' => 0]);
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Error al devolver puntos: ' . $e->getMessage()]);
                    exit;
                }
            }
        }

        $pdo->prepare("DELETE FROM carrito WHERE {$w['campo']} = ?")->execute([$w['valor']]);
        echo json_encode(['success' => true, 'cart_count' => 0]);
        break;

    // ── RESUMEN (totales dinámicos) ───────────────────────────
    case 'resumen':
        $w = getWhere();
        $stmt = $pdo->prepare("SELECT c.cantidad, c.es_canje_puntos, p.precio, p.precio_oferta
                                FROM carrito c
                                JOIN productos p ON c.producto_id = p.id
                                WHERE c.{$w['campo']} = ? AND p.activo = 1");
        $stmt->execute([$w['valor']]);
        $rows = $stmt->fetchAll();

        $subtotal = 0;
        foreach ($rows as $r) {
            // Omitir del subtotal los items que se canjean por puntos
            if (!empty($r['es_canje_puntos'])) continue;
            $precio    = ($r['precio_oferta'] !== null && $r['precio_oferta'] > 0) ? $r['precio_oferta'] : $r['precio'];
            $precio    = precioFinal($precio);
            $subtotal += $precio * $r['cantidad'];
        }
        $envio = $subtotal >= 200 ? 0 : null;
        $total = $subtotal + ($envio ?? 0);

        echo json_encode([
            'success'  => true,
            'subtotal' => $subtotal,
            'envio'    => $envio,
            'total'    => $total,
            'count'    => getCount($pdo),
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        break;
}
?>