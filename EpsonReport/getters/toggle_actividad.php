<?php
// Activa o desactiva un botón de actividad (solo admin); el cambio lo ven de inmediato los promotores y el modal de reportes.
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

$id = (int) ($_POST['id'] ?? 0);
$activa = ($_POST['activa'] ?? '1') === '1';

if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'ID de actividad no válido.']);
	exit;
}

echo json_encode(ep_actividad_activar($id, $activa) ? ['ok' => true, 'activa' => $activa] : ['ok' => false, 'message' => 'No se pudo actualizar la actividad.']);
