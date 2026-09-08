<?php
// Junta todas las importaciones completadas de un canal (opcional filtro trimestre/año) sin sumar montos entre trimestres distintos.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/liquidacion_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$canal = $_GET['canal'] ?? '';
if (!in_array($canal, ['directa', 'distribuidor'], true)) {
	echo json_encode(['ok' => false, 'message' => 'Canal inválido.']);
	exit;
}
$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio = (int) ($_GET['anio'] ?? 0);

$resultado = liquidacion_resumen_pagos_unificado($mysqli, $canal, $trimestre, $anio);

echo json_encode([
	'ok' => true,
	'canal' => $canal,
	'importaciones' => $resultado['importaciones'],
	'filas' => $resultado['filas'],
]);
?>
