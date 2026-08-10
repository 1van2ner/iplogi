<?php
// ============================================================
// CONFIGURACIÓN DE BASE DE DATOS
// Archivo: includes/config.php
// ============================================================

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$localHosts = ['localhost', '127.0.0.1', '::1'];
if (in_array(preg_replace('/:\\d+$/', '', strtolower($host)), $localHosts, true)) {
    // ── ENTORNO LOCAL (XAMPP) ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'iptecnologiadb');
    define('SITE_URL', $scheme . '://' . $host . '/iptecnologia');
} else {
    // ── ENTORNO PRODUCCIÓN (Banahosting) ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'jhlidrtv_admintorres');
    define('DB_PASS', 'AdminTorres123456$');
    define('DB_NAME', 'jhlidrtv_iptecnologiadb');
    define('SITE_URL', 'https://iptecnologiaperu.com');
}

define('SITE_NAME', 'IP Tecnología Perú');
define('COSTO_DELIVERY', 15.00);

// ============================================================
// API PERU (apiperu.dev) — consulta DNI/RUC para autocompletar registro
// El token nunca se debe exponer al navegador; solo se usa desde PHP (server-side).
// ============================================================
define('APIPERU_TOKEN', 'ae2c5b6d4235e930c7a03ef65c0a32a811bed92462fb9f5b25e238cd8fde7d75');
define('CONSULTADNI_API_KEY', 'cdni_13cc123eab33d6eeb7d020bf9b453c8a');
// ============================================================
// CONFIGURACIÓN DE CORREO (SMTP Banahosting)
// ============================================================
define('MAIL_HOST', 'bh8934.banahosting.com');
define('MAIL_USER', 'no-reply@iptecnologiaperu.com');
define('MAIL_PASS', 'TorresAdmin123456$');
define('MAIL_PORT', 465);
define('MAIL_FROM', 'no-reply@iptecnologiaperu.com');
define('MAIL_NAME', 'IP Tecnología Perú');
// Conexión PDO
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die('<div style="padding:20px;background:#f44336;color:white;font-family:sans-serif;">
                <h2>Error de Conexión</h2>
                <p>No se pudo conectar a la base de datos. Verifica que XAMPP esté corriendo.</p>
                <code>' . $e->getMessage() . '</code>
            </div>');
        }
    }
    return $pdo;
}

session_start();

// Funciones de autenticación
function isLoggedIn() {
    return isset($_SESSION['usuario_id']);
}

function isAdmin() {
    if (!isLoggedIn()) return false;
    // Si ya tenemos el rol en sesión, usarlo directamente
    if (isset($_SESSION['rol'])) {
        return $_SESSION['rol'] === 'admin';
    }
    // Fallback: leer el rol desde la BD y guardarlo en sesión
    try {
        $pdo = getDB();
        $s = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1");
        $s->execute([$_SESSION['usuario_id']]);
        $rol = $s->fetchColumn();
        $_SESSION['rol'] = $rol ?: 'cliente';
        return $_SESSION['rol'] === 'admin';
    } catch (Exception $e) {
        return false;
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// Contar items en carrito
function countCarrito() {
    if (isLoggedIn()) {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT SUM(cantidad) FROM carrito WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT SUM(cantidad) FROM carrito WHERE session_id = ?");
        $stmt->execute([session_id()]);
    }
    return (int)$stmt->fetchColumn();
}

function formatPrice($price) {
    return 'S/ ' . number_format($price, 2);
}

// Los precios cargados en la BD son precio de TÉCNICO (base).
// Técnico, Proyectista y Distribuidor pagan ese precio base.
// Cliente Final paga ese precio + 10%, siempre y automáticamente.
define('RECARGO_CLIENTE_FINAL', 0.10);

function precioFinal($precioBase) {
    if ($precioBase === null) return null;
    $precioBase = (float)$precioBase;
    if (isLoggedIn() && ($_SESSION['rol'] ?? '') === 'cliente_final') {
        return round($precioBase * (1 + RECARGO_CLIENTE_FINAL), 2);
    }
    return $precioBase;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateOrderCode() {
    return 'TS-' . strtoupper(substr(md5(uniqid()), 0, 8));
}

function ensureAppSettingsTable() {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `clave` VARCHAR(100) NOT NULL UNIQUE,
        `valor` TEXT NOT NULL,
        `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function getAppSetting($clave, $default = null) {
    ensureAppSettingsTable();
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT valor FROM app_settings WHERE clave = ? LIMIT 1");
    $stmt->execute([$clave]);
    $valor = $stmt->fetchColumn();
    return $valor !== false ? $valor : $default;
}

function setAppSetting($clave, $valor) {
    ensureAppSettingsTable();
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO app_settings (clave, valor) VALUES (?, ?) " .
        "ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
    );
    $stmt->execute([$clave, $valor]);
}

function isBlackFridayActive() {
    return getAppSetting('black_friday_active', '1') === '1';
}

function setBlackFridayActive($active) {
    setAppSetting('black_friday_active', $active ? '1' : '0');
}

// Devuelve el HTML de precio + botón, o un bloque de "inicia sesión" si no hay login
function renderPrecioCarrito($p, $precio, $desc, $idProducto) {
    if (!isLoggedIn()) {
        return '<div class="price-locked">
                    <a href="' . SITE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '/productos.php') . '">
                        <i class="fas fa-lock"></i> Inicia sesión para ver precio
                    </a>
                </div>
                <a href="' . SITE_URL . '/registro.php" class="btn-cart btn-login-required">
                    <i class="fas fa-user-plus"></i> Regístrate para comprar
                </a>';
    }
    $precioBase  = precioFinal($p['precio']);
    $ofertaBase  = $p['precio_oferta'] ? precioFinal($p['precio_oferta']) : null;

    $html = '';
    if ($ofertaBase) {
        $html .= '<span class="price-old">'.formatPrice($precioBase).'</span>'
               . '<span class="price-main">'.formatPrice($ofertaBase)
               . '<span class="price-save"> Ahorras '.formatPrice($precioBase-$ofertaBase).'</span></span>';
    } else {
        $html .= '<span class="price-main">'.formatPrice($precioBase).'</span>';
    }
    if ($p['stock'] > 0) {
        $html .= '<div class="prod-stock"><i class="fas fa-check-circle"></i> En stock ('.$p['stock'].' unid.)</div>'
               . '<button class="btn-cart btn-add-cart" data-id="'.$idProducto.'"><i class="fas fa-cart-plus"></i> Agregar al carrito</button>';
    } else {
        $html .= '<div class="prod-stock out"><i class="fas fa-times-circle"></i> Agotado</div>'
               . '<button class="btn-cart" disabled>Sin stock</button>';
    }
    return $html;
}
?>