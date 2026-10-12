<?php
// Cambia la clave del usuario en sesión (POST actual, clave, clave2). Cualquier rol, solo la suya.
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
	$r = ep_perfil_cambiar_clave((string) ($_POST['actual'] ?? ''), (string) ($_POST['clave'] ?? ''), (string) ($_POST['clave2'] ?? ''));
} catch (Throwable $e) {
	error_log('perfil_clave: '.$e->getMessage());
	$r = ['ok' => false, 'message' => 'No se pudo cambiar la clave. Intenta de nuevo.'];
}
echo json_encode($r);
