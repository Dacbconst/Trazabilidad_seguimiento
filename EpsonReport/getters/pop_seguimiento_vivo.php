<?php
// Seguimiento del equipo: devuelve la vista ya pintada y su firma para el mes pedido (?pop=ID, por defecto el principal); solo supervisores.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!ep_login_check(true, true)) {
	http_response_code(401);
	echo '{}';
	exit;
}
if (($_SESSION['rol'] ?? '') !== 'supervisor') {
	http_response_code(403);
	echo '{}';
	exit;
}
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

try {
	require_once __DIR__.'/../includes/pop_datos.php';
	require_once __DIR__.'/../includes/pop_seguimiento.php';
	$pedido = (int) ($_GET['pop'] ?? 0);
	$mes = $pedido ? ep_pop_obtener($pedido) : ep_pop_mes_del_supervisor($usuarioId);
	// Solo se muestra un mes en el que este supervisor tiene POP asignado.
	if (!$mes || !ep_pop_parte_supervisor($mes, $usuarioId)) {
		echo '{}';
		exit;
	}
	$seg = ep_pop_seguimiento($mes, $usuarioId);
	$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	ob_start();
	require __DIR__.'/../components/pop/seguimiento_vista.php';
	$html = ob_get_clean();
	echo json_encode(['firma' => md5($html), 'html' => $html, 'pop' => (int) $mes['id']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	error_log('pop_seguimiento_vivo: '.$e->getMessage());
	echo '{}';
}
