<?php
require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: application/json');

$pdo    = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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

        // Validar puntos si es canje
        if ($usarPuntos) {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para canjear por puntos']);
                exit;
            }
            if (empty($producto['canje_puntos'])) {
                echo json_encode(['success' => false, 'message' => 'Este producto no se puede canjear por puntos']);
                exit;
            }
            $userStmt = $pdo->prepare("SELECT puntos FROM usuarios WHERE id = ?");
            $userStmt->execute([$_SESSION['usuario_id']]);
            $userRow = $userStmt->fetch();
            $userPoints = (int)($userRow['puntos'] ?? 0);
            $puntosNecesarios = (int)($producto['canje_puntos'] * $cantidad);
            if ($userPoints < $puntosNecesarios) {
                echo json_encode(['success' => false, 'message' => 'No tienes suficientes puntos. Necesitas ' . $puntosNecesarios . ' puntos.']);
                exit;
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
        $row = $pdo->prepare("SELECT c.id, p.stock FROM carrito c JOIN productos p ON c.producto_id = p.id WHERE c.id = ? AND c.{$w['campo']} = ?");
        $row->execute([$carritoId, $w['valor']]);
        $item = $row->fetch();

        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item no encontrado']);
            exit;
        }

        $cantidad = min($cantidad, $item['stock']);
        $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$cantidad, $carritoId]);

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
        $pdo->prepare("DELETE FROM carrito WHERE id = ? AND {$w['campo']} = ?")->execute([$carritoId, $w['valor']]);

        echo json_encode(['success' => true, 'cart_count' => getCount($pdo)]);
        break;

    // ── VACIAR CARRITO ────────────────────────────────────────
    case 'vaciar':
        $w = getWhere();
        $pdo->prepare("DELETE FROM carrito WHERE {$w['campo']} = ?")->execute([$w['valor']]);
        echo json_encode(['success' => true, 'cart_count' => 0]);
        break;

    // ── RESUMEN (totales dinámicos) ───────────────────────────
    case 'resumen':
        $w = getWhere();
        $stmt = $pdo->prepare("SELECT c.cantidad, p.precio, p.precio_oferta
                                FROM carrito c
                                JOIN productos p ON c.producto_id = p.id
                                WHERE c.{$w['campo']} = ? AND p.activo = 1");
        $stmt->execute([$w['valor']]);
        $rows = $stmt->fetchAll();

        $subtotal = 0;
        foreach ($rows as $r) {
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