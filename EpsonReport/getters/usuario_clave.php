<?php
// Cambia la clave de un usuario (POST id, clave, clave2). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/usuarios_datos.php';

try {
	$r = ep_usuario_cambiar_clave((int) ($_POST['id'] ?? 0), (string) ($_POST['clave'] ?? ''), (string) ($_POST['clave2'] ?? ''));
} catch (Throwable $e) {
	error_log('usuario_clave: '.$e->getMessage());
	$r = ['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()];
}
echo json_encode($r);
