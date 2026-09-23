<?php
// Actas de un usuario para el tipo activo (todas|rebate|cabeceras|rumas|perchas). Mismo chequeo de rol que negociacion_resumen.php: expone Actas ajenas.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

if (!isset($_GET['usuario_id']) || $_GET['usuario_id'] === '') {
	echo json_encode(['ok' => false, 'message' => 'Falta el usuario.']);
	exit;
}
$usuarioId = (int) $_GET['usuario_id'];
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);
$canal     = in_array($_GET['canal'] ?? '', ['directo', 'distribuidor'], true) ? $_GET['canal'] : 'total';
$tipo      = in_array($_GET['tipo'] ?? '', ['todas', 'rebate', 'cabeceras', 'rumas', 'perchas'], true) ? $_GET['tipo'] : 'todas';

echo json_encode(['ok' => true, 'actas' => listar_actas_negociacion_usuario($mysqli, $usuarioId, $trimestre, $anio, $tipo, $canal)]);
?>
