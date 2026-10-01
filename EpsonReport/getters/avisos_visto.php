<?php
// Marca como vistos los avisos del promotor en sesión (POST sin datos).
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usuario_id'])) {
	http_response_code(401);
	echo json_encode(['ok' => false]);
	exit;
}

require_once __DIR__.'/../includes/avisos_datos.php';

try {
	echo json_encode(['ok' => ep_avisos_marcar_visto((int) $_SESSION['usuario_id'])]);
} catch (Throwable $e) {
	error_log('avisos_visto: '.$e->getMessage());
	echo json_encode(['ok' => false]);
}
