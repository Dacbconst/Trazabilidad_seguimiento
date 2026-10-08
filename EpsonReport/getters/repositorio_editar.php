<?php
// Cambia el nombre de un material o campaña del repositorio de POP (POST id, nombre). Solo Fabricio o el admin.
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
	$id = (int) ($_POST['id'] ?? 0);
	$nombre = (string) ($_POST['nombre'] ?? '');
	$error = ep_pop_catalogo_renombrar($id, $nombre);
	if ($error === null) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		ep_auditar('repositorio_editar', 'repositorio', $id, 'Renombró un nombre del repositorio de POP', [
			ep_auditoria_dato('Nuevo nombre', $nombre),
		]);
	}
} catch (Throwable $e) {
	error_log('repositorio_editar: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
$catalogo = ep_pop_catalogo();
echo json_encode($error === null ? ['ok' => true, 'material' => $catalogo['materiales'], 'campana' => $catalogo['campanas']] : ['ok' => false, 'message' => $error], JSON_UNESCAPED_UNICODE);
