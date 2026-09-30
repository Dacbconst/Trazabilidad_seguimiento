<?php
// Crea un usuario (sin id) o cambia correo y rol de uno existente (POST). Solo admin.
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

// En este servidor un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	$correo = (string) ($_POST['correo'] ?? '');
	$rol = (string) ($_POST['rol'] ?? '');
	if ($id > 0) {
		$r = ep_usuario_actualizar($id, $correo, $rol);
	} else {
		$r = ep_usuario_crear((string) ($_POST['usuario'] ?? ''), (string) ($_POST['nombre'] ?? ''), $correo, $rol, (string) ($_POST['clave'] ?? ''), (string) ($_POST['clave2'] ?? ''));
	}
} catch (Throwable $e) {
	error_log('usuario_guardar: '.$e->getMessage());
	$r = ['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()];
}
echo json_encode($r);
