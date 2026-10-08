<?php
// Activa o desactiva un botón de actividad (solo admin); el cambio lo ven de inmediato los promotores y el modal de reportes.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
// La acción deja desactualizada una caché de sesión (ver ep_cache_sesion).
unset($_SESSION['ep_cache']['act_visibles']);
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

if (!ep_actividad_activar($id, $activa)) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo actualizar la actividad.']);
	exit;
}
$actividad = current(array_filter(ep_actividades(), fn($a) => $a['id'] === $id));
if ($actividad) {
	require_once __DIR__.'/../includes/auditoria_datos.php';
	ep_auditar($activa ? 'actividad_activar' : 'actividad_desactivar', 'actividad', $id, ($activa ? 'Activó' : 'Desactivó').' la actividad «'.$actividad['label'].'»');
}
echo json_encode(['ok' => true, 'activa' => $activa]);
