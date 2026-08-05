<?php
// Iniciar sesión ANTES de cualquier output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/includes/config.php';
header('Content-Type: application/json');

$pdo    = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
        if (!isLoggedIn()) {
            echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para agregar productos al carrito', 'require_login' => true]);
            exit;
        }

        $productoId = (int)($_POST['producto_id'] ?? 0);
        $cantidad   = max(1, (int)($_POST['cantidad'] ?? 1));

        if (!$productoId) {
            echo json_encode(['success' => false, 'message' => 'Producto inválido']);
            exit;
        }

        $usarPuntos = isset($_POST['usar_puntos']) && $_POST['usar_puntos'] == '1';
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

        $w = getWhere();

        if ($usarPuntos) {
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión para canjear con puntos', 'require_login' => true]);
                exit;
            }
            $stmtPuntos = $pdo->prepare("SELECT puntos FROM usuarios WHERE id = ?");
            $stmtPuntos->execute([$_SESSION['usuario_id']]);
            $puntosUsuario = (int)$stmtPuntos->fetchColumn();
            $puntosNecesarios = (int)$producto['canje_puntos'];
            if ($puntosNecesarios <= 0) {
                echo json_encode(['success' => false, 'message' => 'Este producto no puede ser canjeado con puntos']);
                exit;
            }
            if ($puntosUsuario < $puntosNecesarios) {
                echo json_encode(['success' => false, 'message' => 'No tienes suficientes puntos para canjear este producto']);
                exit;
            }
        }

        $check = $pdo->prepare("SELECT id, cantidad FROM carrito WHERE producto_id = ? AND {$w['campo']} = ? AND es_canje_puntos = ?");
        $check->execute([$productoId, $w['valor'], $usarPuntos ? 1 : 0]);
        $existing = $check->fetch();

        if ($existing) {
            $nueva = min($existing['cantidad'] + $cantidad, $producto['stock']);
            $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?")->execute([$nueva, $existing['id']]);
        } else {
            if (isLoggedIn()) {
                $pdo->prepare("INSERT INTO carrito (usuario_id, session_id, producto_id, cantidad, es_canje_puntos, puntos_canjeados) VALUES (?, NULL, ?, ?, ?, ?)")
                    ->execute([$_SESSION['usuario_id'], $productoId, $cantidad, $usarPuntos ? 1 : 0, $usarPuntos ? $producto['canje_puntos'] : 0]);
            } else {
                $pdo->prepare("INSERT INTO carrito (usuario_id, session_id, producto_id, cantidad, es_canje_puntos, puntos_canjeados) VALUES (NULL, ?, ?, ?, ?, ?)")
                    ->execute([session_id(), $productoId, $cantidad, 0, 0]);
            }
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
        $envio = $subtotal >= 200 ? 0 : COSTO_DELIVERY;
        $total = $subtotal + $envio;

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