<?php
// Revisa los comentarios de un calendario ya cerrado y lo deja listo para descargar el PPT (POST id, comentarios). Después ya no se pueden cambiar.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	if ($id <= 0 || !ep_calendario_permitido($id)) {
		echo json_encode(['ok' => false, 'message' => 'Ese calendario no es tuyo.']);
		exit;
	}
	$antes = ep_calendario_obtener($id);
	$comentarios = ep_calendario_limpiar_comentarios((string) ($_POST['comentarios'] ?? ''));
	if (!ep_calendario_finalizar($id, $comentarios)) {
		echo json_encode(['ok' => false, 'message' => 'Este calendario ya estaba finalizado.']);
		exit;
	}
	require_once __DIR__.'/../includes/auditoria_datos.php';
	$detalle = array_filter([$comentarios !== ($antes['comentarios'] ?? null) ? ep_auditoria_cambio('Comentarios', (string) ($antes['comentarios'] ?? ''), (string) $comentarios) : null]);
	ep_auditar('calendario_finalizar', 'calendario', $id, 'Finalizó el reporte de «'.ep_calendario_nombre($antes).'»', $detalle);
} catch (Throwable $e) {
	error_log('calendario_finalizar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode(['ok' => true]);
