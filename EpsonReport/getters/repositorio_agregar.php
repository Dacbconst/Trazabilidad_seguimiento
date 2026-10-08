<?php
// Agrega nombres a un repositorio de POP (POST tipo = material|campana, texto = uno por línea). Solo Fabricio o el admin.
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
	$tipo = (string) ($_POST['tipo'] ?? '');
	if (!isset(EP_POP_CATALOGO_LARGO[$tipo])) {
		echo json_encode(['ok' => false, 'message' => 'Repositorio desconocido.']);
		exit;
	}
	$nombres = preg_split('/\R/u', (string) ($_POST['texto'] ?? ''));
	$nuevos = ep_pop_catalogo_agregar($tipo, $nombres, (int) $_SESSION['usuario_id']);
	if ($nuevos > 0) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('repositorio_agregar', 'repositorio', 0, 'Agregó '.$nuevos.' al repositorio de '.($tipo === 'material' ? 'materiales' : 'campañas'), [
			ep_auditoria_dato('Agregados', $nuevos),
		]);
	}
} catch (Throwable $e) {
	error_log('repositorio_agregar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
$catalogo = ep_pop_catalogo();
echo json_encode(['ok' => true, 'agregados' => $nuevos, 'lista' => $catalogo[$tipo === 'material' ? 'materiales' : 'campanas']], JSON_UNESCAPED_UNICODE);
