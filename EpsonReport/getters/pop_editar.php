<?php
// Corrige el material del mes de POP mientras siga abierto (solo admin/supervisor, y solo el suyo).
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

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$id = (int) ($_POST['id'] ?? 0);
	if (!ep_pop_permitido($id)) {
		echo json_encode(['ok' => false, 'message' => 'Ese mes de POP no es tuyo.']);
		exit;
	}
	$pop = ep_pop_obtener($id);
	if ($pop['estado'] !== 'activo') {
		echo json_encode(['ok' => false, 'message' => 'Ese mes ya está cerrado.']);
		exit;
	}
	$filas = ep_pop_filas_desde_post($_POST['filas'] ?? '[]');
	if (is_string($filas)) {
		echo json_encode(['ok' => false, 'message' => $filas]);
		exit;
	}
	// Un material que los promotores ya reportaron no se puede quitar: su registro quedaría sin bodega en el PPT.
	$quedan = array_column($filas, 'material');
	foreach (ep_pop_entregado($pop['mes']) as $clave => $t) {
		$material = substr($clave, strpos($clave, '|') + 1);
		if (($t['canales'] + $t['retail']) > 0 && !in_array($material, $quedan, true)) {
			echo json_encode(['ok' => false, 'message' => 'No puedes quitar «'.$material.'»: los promotores ya reportaron ese material este mes.']);
			exit;
		}
	}
	$comentarios = ep_pop_limpiar_comentarios((string) ($_POST['comentarios'] ?? ''));
	$ok = ep_pop_editar($id, $filas, $comentarios);
	if ($ok) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('pop_editar', 'pop', $id, 'Corrigió el material POP de '.ep_pop_mes_texto($pop['mes']), [
			ep_auditoria_cambio('Materiales', (string) count($pop['filas']), (string) count($filas)),
			ep_auditoria_cambio('Total en bodega', (string) array_sum(array_column($pop['filas'], 'bodega')), (string) array_sum(array_column($filas, 'bodega'))),
		]);
	}
} catch (Throwable $e) {
	error_log('pop_editar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'message' => 'No se pudieron guardar los cambios.']);
