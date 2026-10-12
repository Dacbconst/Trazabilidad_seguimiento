<?php
// Guarda el correo del usuario en sesión (POST correo). Cualquier rol, solo el suyo.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/perfil_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Tu sesión se cerró. Entra de nuevo.']);
	exit;
}

try {
	$r = ep_perfil_guardar_correo((string) ($_POST['correo'] ?? ''));
} catch (Throwable $e) {
	error_log('perfil_correo: '.$e->getMessage());
	$r = ['ok' => false, 'message' => 'No se pudo guardar. Intenta de nuevo.'];
}
echo json_encode($r);
