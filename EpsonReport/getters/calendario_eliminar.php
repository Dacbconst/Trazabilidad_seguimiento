<?php
// Quita un calendario de la lista (borrado lógico, POST id). Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	$cal = ep_calendario_obtener($id);
	$ok = ep_calendario_eliminar($id);
	if ($ok && $cal) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('calendario_eliminar', 'calendario', $id, 'Eliminó el calendario «'.ep_calendario_nombre($cal).'»', [
			ep_auditoria_dato('Canal', $cal['canal']),
			ep_auditoria_dato('Estado al eliminar', $cal['estado']),
		]);
	}
} catch (Throwable $e) {
	error_log('calendario_eliminar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'message' => 'El calendario no existe o ya estaba eliminado.']);
