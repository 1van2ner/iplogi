<?php
require_once '../includes/config.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Debes iniciar sesión para comparar productos']);
    exit;
}

$idsRaw = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $idsRaw)));
if (count($ids) < 2 || count($ids) > 3) { echo json_encode(['error'=>'2 a 3 productos']); exit; }

$pdo = getDB();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $s = $pdo->prepare("SELECT p.*,c.nombre as cat_nombre FROM productos p JOIN categorias c ON p.categoria_id=c.id WHERE p.id IN ($placeholders) AND p.activo=1");
    $s->execute($ids);
    $prods = $s->fetchAll();
} catch(Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
    exit;
}

$resultado = [];
foreach ($prods as $p) {
    $precio = ($p['precio_oferta'] && $p['precio_oferta'] > 0) ? $p['precio_oferta'] : $p['precio'];
    $resultado[] = [
        'id'              => $p['id'],
        'nombre'          => $p['nombre'],
        'marca'           => $p['marca'],
        'modelo'          => $p['modelo'] ?? '',
        'cat_nombre'      => $p['cat_nombre'],
        'precio_fmt'      => formatPrice($precio),
        'precio_num'      => $precio,
        'stock'           => $p['stock'],
        'imagen'          => $p['imagen'] ?? '',
        'descripcion'     => $p['descripcion'] ? substr($p['descripcion'], 0, 120).'...' : '',
        'especificaciones'=> $p['especificaciones'] ?? '',
        'potencia_va'     => isset($p['potencia_va']) && $p['potencia_va'] ? $p['potencia_va'].' VA' : '',
    ];
}

echo json_encode(['productos' => $resultado]);
