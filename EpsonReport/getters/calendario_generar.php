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
$reporteId = $cal ? ep_calendario_generar_ahora($id, (int) $_SESSION['usuario_id']) : null;
if ($reporteId === null) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo generar el reporte.']);
	exit;
}
require_once __DIR__.'/../includes/auditoria_datos.php';
if ($reporteId > 0) {
	ep_auditar('calendario_generar', 'calendario', $id, 'Cerró «'.ep_calendario_nombre($cal).'» y generó su reporte antes del plazo', ep_calendario_detalle_cierre($id));
	echo json_encode(['ok' => true]);
	exit;
}
// Se cerró igual (lo pidió el admin), pero sin reporte: hay que avisarle, no decir que salió bien.
ep_auditar('calendario_generar_sin_reporte', 'calendario', $id, 'Cerró «'.ep_calendario_nombre($cal).'» antes del plazo, pero no se generó el reporte: sus registros ya estaban en otro reporte mensual activo', ep_calendario_detalle_cierre($id));
echo json_encode(['ok' => true, 'aviso' => 'El calendario se cerró, pero no se generó el reporte: sus registros ya estaban en otro reporte mensual activo. Elimina ese reporte y reactiva este calendario para intentarlo de nuevo.']);
