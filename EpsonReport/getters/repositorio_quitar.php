<?php
// Quita un nombre de un repositorio de POP (borrado lógico, POST id). Los meses ya cargados no se tocan. Solo Fabricio o el admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/pop_reparto.php';
require_once __DIR__.'/../includes/pop_catalogo.php';

if (!ep_pop_es_dueno()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo Fabricio o un administrador manejan los repositorios.']);
	exit;
}

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$quitado = ep_pop_catalogo_quitar((int) ($_POST['id'] ?? 0));
	if ($quitado) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('repositorio_quitar', 'repositorio', (int) $_POST['id'], 'Quitó «'.$quitado['nombre'].'» del repositorio de '.($quitado['tipo'] === 'material' ? 'materiales' : 'campañas'));
	}
} catch (Throwable $e) {
	error_log('repositorio_quitar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($quitado ? ['ok' => true] : ['ok' => false, 'message' => 'Ese nombre no existe o ya estaba quitado.']);
