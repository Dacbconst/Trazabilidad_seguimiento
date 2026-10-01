<?php
// Devuelve un registro pendiente al promotor con un motivo obligatorio (POST codigo, motivo). Admin, o el supervisor al que le toca.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No tienes permiso para esto.']);
	exit;
}

try {
	require_once __DIR__.'/../includes/aprobacion_datos.php';
	echo json_encode(ep_devolver_registro(trim((string) ($_POST['codigo'] ?? '')), (string) ($_POST['motivo'] ?? '')));
} catch (Throwable $e) {
	error_log('aprobacion_devolver: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
}
