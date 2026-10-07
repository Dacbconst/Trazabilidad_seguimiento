<?php
// Cierra el mes de POP y genera su reporte mensual (solo admin/supervisor, y solo el suyo).
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/pop_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	if (!ep_pop_permitido($id)) {
		echo json_encode(['ok' => false, 'message' => 'Ese mes de POP no es tuyo.']);
		exit;
	}
	$pop = ep_pop_obtener($id);
	$reporteId = ep_pop_cerrar($id, (int) $_SESSION['usuario_id']);
	if ($reporteId === null) {
		echo json_encode(['ok' => false, 'message' => 'No se pudo cerrar el mes. Recarga la página.']);
		exit;
	}
	require_once __DIR__.'/../includes/auditoria_datos.php';
	$mesTxt = ep_pop_mes_texto($pop['mes']);
	if ($reporteId > 0) {
		ep_auditar('pop_cerrar', 'pop', $id, 'Cerró el mes de POP de '.$mesTxt.' y generó su reporte', [ep_auditoria_dato('Reporte', '#'.$reporteId)]);
	} else {
		ep_auditar('pop_cerrar_sin_reporte', 'pop', $id, 'Cerró el mes de POP de '.$mesTxt.', pero no se generó el reporte: no había registros aprobados libres', []);
	}
} catch (Throwable $e) {
	error_log('pop_cerrar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($reporteId > 0
	? ['ok' => true, 'reporte_id' => $reporteId]
	: ['ok' => true, 'aviso' => 'El mes quedó cerrado, pero no se generó el reporte: no hay registros aprobados de POP de ese mes, o ya estaban en otro reporte.']);
