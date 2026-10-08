<?php
// Un supervisor marca qué promotor de su equipo reporta qué material del mes (POST id = mes, asignaciones = {promotor_id: [fila_id, ...]}). Sin cantidades.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
// La acción deja desactualizada una caché de sesión (ver ep_cache_sesion).
unset($_SESSION['ep_cache']['avisos'], $_SESSION['ep_cache']['act_visibles']);
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'supervisor') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo un supervisor elige a los promotores de su equipo.']);
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
	$asignaciones = json_decode((string) ($_POST['asignaciones'] ?? '{}'), true);
	$resultado = ep_pop_equipo_guardar($pop, $yo, is_array($asignaciones) ? $asignaciones : []);
	if ($resultado !== true) {
		echo json_encode(['ok' => false, 'message' => $resultado]);
		exit;
	}
	require_once __DIR__.'/../includes/auditoria_datos.php';
	$nombres = array_column(ep_pop_equipo($yo), 'nombre', 'id');
	$materiales = array_column($pop['filas'], 'material', 'id');
	$detalle = [];
	foreach (ep_pop_equipo_de((int) $pop['id'], $yo) as $promotorId => $filas) {
		$detalle[] = ep_auditoria_dato($nombres[$promotorId] ?? 'Promotor #'.$promotorId, implode(', ', array_map(fn($f) => $materiales[$f] ?? '#'.$f, $filas)));
	}
	ep_auditar('pop_equipo', 'pop', (int) $pop['id'], 'Marcó su equipo para el POP de '.ep_pop_mes_texto($pop['mes']), $detalle);
} catch (Throwable $e) {
	error_log('pop_equipo: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode(['ok' => true]);
