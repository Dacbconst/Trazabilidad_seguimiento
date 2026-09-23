<?php
// Quita un reporte mensual del histórico (borrado lógico, POST id). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/reportes_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check() || ep_rol_actual() !== 'admin') {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'No tienes permiso para esto.']);
	exit;
}
$ok = ep_reporte_eliminar((int) ($_POST['id'] ?? 0));
echo json_encode(['success' => $ok, 'error' => $ok ? null : 'No se pudo quitar el reporte.']);
