<?php
// Quita un mes de POP de la lista (borrado lógico, POST id). Solo Fabricio o el admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/pop_datos.php';

if (!ep_pop_es_dueno()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo Fabricio o un administrador manejan el material POP.']);
	exit;
}

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	if (!ep_pop_permitido($id)) {
		echo json_encode(['ok' => false, 'message' => 'Ese mes de POP no es tuyo.']);
		exit;
	}
	$pop = ep_pop_obtener($id);
	$ok = ep_pop_eliminar($id);
	if ($ok) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('pop_eliminar', 'pop', $id, 'Eliminó el mes de POP de '.ep_pop_mes_texto($pop['mes']), [
			ep_auditoria_dato('Estado al eliminar', $pop['estado']),
			ep_auditoria_dato('Materiales', count($pop['filas'])),
		]);
	}
} catch (Throwable $e) {
	error_log('pop_eliminar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'message' => 'El mes no existe o ya estaba eliminado.']);
