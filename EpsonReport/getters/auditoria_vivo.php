<?php
// Firma liviana de la bitácora para la pantalla de Auditoría en vivo (solo admin).
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!ep_login_check()) {
	http_response_code(401);
	echo '{}';
	exit;
}
if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo '{}';
	exit;
}
session_write_close();

require_once __DIR__.'/../includes/en_vivo_datos.php';
echo json_encode(ep_vivo_auditoria());
