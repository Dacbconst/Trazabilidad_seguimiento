<?php
// "Generar ahora" (solo admin): cierra el calendario antes de tiempo y genera el reporte mensual, igual que al vencer.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$id = (int) ($_POST['id'] ?? 0);
if (!ep_calendario_permitido($id)) {
	echo json_encode(['ok' => false, 'message' => 'Ese calendario no es tuyo.']);
	exit;
}
if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Calendario no válido.']);
	exit;
}

$cal = ep_calendario_obtener($id);
if (!$cal || !ep_calendario_generar_ahora($id, (int) $_SESSION['usuario_id'])) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo generar el reporte.']);
	exit;
}
require_once __DIR__.'/../includes/auditoria_datos.php';
ep_auditar('calendario_generar', 'calendario', $id, 'Cerró «'.ep_calendario_nombre($cal).'» y generó su reporte antes del plazo', ep_calendario_detalle_cierre($id));
echo json_encode(['ok' => true]);
