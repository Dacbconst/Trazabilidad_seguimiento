<?php
// Activa o desactiva una actividad en la sesión (solo admin) para que se refleje de inmediato en reportes y actividades.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$id = (int) ($_POST['id'] ?? 0);
$activa = ($_POST['activa'] ?? '1') === '1';

if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'ID de actividad no válido.']);
	exit;
}

$_SESSION['ep_actividades_desactivadas'] = $_SESSION['ep_actividades_desactivadas'] ?? [];
if (!$activa) {
	if (!in_array($id, $_SESSION['ep_actividades_desactivadas'], true)) {
		$_SESSION['ep_actividades_desactivadas'][] = $id;
	}
} else {
	$_SESSION['ep_actividades_desactivadas'] = array_values(array_filter(
		$_SESSION['ep_actividades_desactivadas'],
		fn($dId) => (int) $dId !== $id
	));
}

echo json_encode(['ok' => true, 'activa' => $activa]);
