<?php
// Estado liviano del Calendario para la pantalla en vivo del admin; también cierra al vencer los calendarios que ya cumplieron su plazo.
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
if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo '{}';
	exit;
}
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

try {
	require_once __DIR__.'/../includes/calendario_datos.php';
	require_once __DIR__.'/../includes/en_vivo_datos.php';
	ep_calendario_cruzar_pendientes();
	ep_calendario_verificar_vencidos($usuarioId);
	ep_calendario_cerrar_completos();
	echo json_encode(ep_vivo_calendario());
} catch (Throwable $e) {
	error_log('calendario_vivo: '.$e->getMessage());
	echo '{}';
}
