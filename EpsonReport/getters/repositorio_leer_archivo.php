<?php
// Lee un .xlsx/.csv/.txt y devuelve sus nombres para revisarlos antes de agregar; no guarda nada (POST tipo + archivo). Solo Fabricio o el admin.
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
require_once __DIR__.'/../includes/xlsx_lector.php';

if (!ep_pop_es_dueno()) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'Solo Fabricio o un administrador manejan los repositorios.']);
	exit;
}

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$tipo = (string) ($_POST['tipo'] ?? '');
	$archivo = $_FILES['archivo'] ?? null;
	if (!isset(EP_POP_CATALOGO_LARGO[$tipo]) || !$archivo || $archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] > 2 * 1024 * 1024) {
		echo json_encode(['ok' => false, 'message' => 'Elige un archivo de hasta 2 MB.']);
		exit;
	}
	$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
	$valores = ep_xlsx_primera_columna($archivo['tmp_name'], $extension);
	// La primera fila suele ser el encabezado de la plantilla.
	if ($valores && in_array(mb_strtoupper($valores[0], 'UTF-8'), ['MATERIAL', 'CAMPAÑA', 'CAMPANA', 'NOMBRE'], true)) {
		array_shift($valores);
	}
} catch (Throwable $e) {
	error_log('repositorio_leer_archivo: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo leer el archivo.']);
	exit;
}
echo json_encode(['ok' => true, 'nombres' => $valores], JSON_UNESCAPED_UNICODE);
