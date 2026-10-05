<?php
// Firma liviana de Reportes mensuales para el rótulo "En vivo" (admin/supervisor).
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!ep_login_check()) {
	http_response_code(401);
	echo '{}';
	exit;
}
if (!ep_es_gestor()) {
	http_response_code(403);
	echo '{}';
	exit;
}
session_write_close();

require_once __DIR__.'/../includes/en_vivo_datos.php';
echo json_encode(ep_vivo_reportes());
