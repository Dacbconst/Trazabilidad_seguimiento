<?php
// Crea un botón de actividad nuevo copiando la lógica (plantilla, campos y fotos) de uno existente. Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/actividades_datos.php';

$nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 80);
$origenId = (int) ($_POST['logica_id'] ?? 0);

if ($nombre === '') {
	echo json_encode(['ok' => false, 'message' => 'Ponle un nombre a la actividad.']);
	exit;
}

$origen = null;
foreach (ep_actividades() as $a) {
	if ($a['id'] === $origenId) {
		$origen = $a;
		break;
	}
}
if (!$origen) {
	echo json_encode(['ok' => false, 'message' => 'Elige de qué actividad copiar la lógica.']);
	exit;
}

$error = ep_actividad_crear($nombre, $origen['plantilla'], (int) $_SESSION['usuario_id']);
echo json_encode($error === null ? ['ok' => true] : ['ok' => false, 'message' => $error]);
