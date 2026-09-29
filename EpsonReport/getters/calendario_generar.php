<?php
// "Generar ahora" (solo admin): cierra el calendario antes de tiempo y genera el reporte mensual, igual que al vencer.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Calendario no válido.']);
	exit;
}

echo json_encode(ep_calendario_generar_ahora($id, (int) $_SESSION['usuario_id']) ? ['ok' => true] : ['ok' => false, 'message' => 'No se pudo generar el reporte.']);
