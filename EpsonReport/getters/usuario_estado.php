<?php
// Desactiva o reactiva un usuario (POST id, activo 1|0). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/usuarios_datos.php';

try {
	$r = ep_usuario_cambiar_estado((int) ($_POST['id'] ?? 0), ($_POST['activo'] ?? '') === '1');
} catch (Throwable $e) {
	error_log('usuario_estado: '.$e->getMessage());
	$r = ['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()];
}
echo json_encode($r);
