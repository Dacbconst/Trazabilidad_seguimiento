<?php
// Ciudad y canal de cada usuario según su rutero (consulta lenta con caché de 5 min); lo pide Usuarios después de pintar la lista. Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}
// La consulta tarda unos segundos: se suelta la sesión para no bloquear otras pestañas del mismo usuario.
session_write_close();

require_once __DIR__.'/../includes/usuarios_datos.php';
require_once __DIR__.'/../includes/pdv_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	echo json_encode(['ok' => true, 'rutero' => (object) ep_usuarios_rutero(ep_db())], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
	error_log('usuarios_rutero: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor.']);
}
