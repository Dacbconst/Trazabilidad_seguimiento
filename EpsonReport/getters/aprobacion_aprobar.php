<?php
// Aprueba un registro pendiente (POST codigo). Admin, o el supervisor al que le toca.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No tienes permiso para esto.']);
	exit;
}

// En este servidor un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	require_once __DIR__.'/../includes/aprobacion_datos.php';
	echo json_encode(ep_aprobar_registro(trim((string) ($_POST['codigo'] ?? ''))));
} catch (Throwable $e) {
	error_log('aprobacion_aprobar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
}
