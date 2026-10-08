<?php
// Un supervisor reparte entre sus promotores el POP que le tocó este mes (POST filas = [{fila_id, reparto: {promotor_id: cantidad}}]).
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'supervisor') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo un supervisor reparte el POP a su equipo.']);
	exit;
}

require_once __DIR__.'/../includes/pop_datos.php';

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$pop = ep_pop_obtener((int) ($_POST['id'] ?? 0));
	if (!$pop) {
		echo json_encode(['ok' => false, 'message' => 'Ese mes de POP no existe.']);
		exit;
	}
	$yo = (int) $_SESSION['usuario_id'];
	$resultado = ep_pop_repartir($pop, $yo, json_decode((string) ($_POST['filas'] ?? '[]'), true) ?: []);
	if ($resultado !== true) {
		echo json_encode(['ok' => false, 'message' => $resultado]);
		exit;
	}
	require_once __DIR__.'/../includes/auditoria_datos.php';
	$nombres = array_column(ep_pop_equipo($yo), 'nombre', 'id');
	$materiales = array_column($pop['filas'], 'material', 'id');
	$detalle = [];
	foreach (ep_pop_asignaciones((int) $pop['id'], 2, $yo) as $filaId => $porPromotor) {
		foreach ($porPromotor as $promotorId => $cantidad) {
			$detalle[] = ep_auditoria_dato(($materiales[$filaId] ?? 'Material').' → '.($nombres[$promotorId] ?? 'Promotor'), $cantidad);
		}
	}
	ep_auditar('pop_repartir', 'pop', (int) $pop['id'], 'Repartió el POP de '.ep_pop_mes_texto($pop['mes']).' a su equipo', $detalle);
} catch (Throwable $e) {
	error_log('pop_repartir: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode(['ok' => true]);
