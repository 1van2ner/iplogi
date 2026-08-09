<?php
/**
 * Endpoint AJAX: consulta DNI o RUC en apiperu.dev y devuelve nombre/apellido y fecha de nacimiento
 * listos para autocompletar el formulario de registro.
 *
 * El token de apiperu.dev vive SOLO aquí (server-side), nunca se envía al navegador.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$tipo   = strtoupper(trim($_POST['tipo']   ?? ''));
$numero = preg_replace('/\D/', '', $_POST['numero'] ?? '');

function respuesta($ok, $data = [], $mensaje = '') {
    echo json_encode(array_merge(['success' => $ok, 'message' => $mensaje], $data));
    exit;
}

if (!in_array($tipo, ['DNI', 'RUC'])) {
    respuesta(false, [], 'Tipo de documento inválido.');
}
if ($tipo === 'DNI' && !preg_match('/^\d{8}$/', $numero)) {
    respuesta(false, [], 'El DNI debe tener 8 dígitos.');
}
if ($tipo === 'RUC' && !preg_match('/^\d{11}$/', $numero)) {
    respuesta(false, [], 'El RUC debe tener 11 dígitos.');
}

$endpoint = $tipo === 'DNI' ? 'https://apiperu.dev/api/dni' : 'https://apiperu.dev/api/ruc';
$campo    = $tipo === 'DNI' ? 'dni' : 'ruc';

$params = json_encode([$campo => $numero]);

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL            => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_POSTFIELDS     => $params,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . APIPERU_TOKEN,
    ],
]);
$respuestaCruda = curl_exec($curl);
$curlErr        = curl_error($curl);
curl_close($curl);

if ($curlErr) {
    respuesta(false, [], 'No se pudo conectar al servicio de verificación. Completa tus datos manualmente.');
}

$json = json_decode($respuestaCruda, true);

if (!$json || empty($json['success']) || empty($json['data'])) {
    respuesta(false, [], ($tipo === 'DNI' ? 'DNI' : 'RUC') . ' no encontrado. Completa tus datos manualmente.');
}

$d = $json['data'];

if ($tipo === 'DNI') {
    $nombres   = trim($d['nombres'] ?? '');
    $apPaterno = trim($d['apellido_paterno'] ?? '');
    $apMaterno = trim($d['apellido_materno'] ?? '');
    $apellido  = trim($apPaterno . ' ' . $apMaterno);
    
    // Capturamos la fecha de nacimiento que devuelve apiperu.dev
    $fechaNacimiento = trim($d['fecha_nacimiento'] ?? '');

    if ($nombres === '' && $apellido === '') {
        respuesta(false, [], 'No se encontraron datos para este DNI. Completa tus datos manualmente.');
    }

    respuesta(true, [
        'nombre'           => $nombres,
        'apellido'         => $apellido,
        'fecha_nacimiento' => $fechaNacimiento
    ]);
} else {
    // RUC: razón social (empresa)
    $razonSocial = trim($d['nombre_o_razon_social'] ?? '');
    if ($razonSocial === '') {
        respuesta(false, [], 'No se encontraron datos para este RUC. Completa tus datos manualmente.');
    }

    respuesta(true, [
        'nombre'    => $razonSocial,
        'apellido'  => '',
        'direccion' => trim($d['direccion'] ?? ''),
    ]);
}