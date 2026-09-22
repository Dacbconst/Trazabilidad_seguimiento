<?php
// Crea una actividad nueva copiando la lógica (plantilla/campos/fotos) de una ya existente — mock en sesión, sin tabla real todavía.
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

$nombre = trim($_POST['nombre'] ?? '');
$logicaId = (int) ($_POST['logica_id'] ?? 0);

if ($nombre === '') {
	echo json_encode(['ok' => false, 'message' => 'Ponle un nombre a la actividad.']);
	exit;
}

$actividades = ep_actividades();
$origen = null;
foreach ($actividades as $a) {
	if ($a['id'] === $logicaId) { $origen = $a; break; }
}
if (!$origen) {
	echo json_encode(['ok' => false, 'message' => 'Elige de qué actividad copiar la lógica.']);
	exit;
}

$nuevoId = max(array_column($actividades, 'id')) + 1;

$_SESSION['ep_actividades_extra'] = $_SESSION['ep_actividades_extra'] ?? [];
$_SESSION['ep_actividades_extra'][] = [
	'id' => $nuevoId,
	'label' => $nombre,
	'badge' => 'Nuevo',
	'plantilla' => $origen['plantilla'],
	'campos' => $origen['campos'],
	'sin_estadisticas' => $origen['sin_estadisticas'] ?? false,
	'render_id' => $origen['render_id'] ?? $origen['id'],
];

echo json_encode(['ok' => true]);
