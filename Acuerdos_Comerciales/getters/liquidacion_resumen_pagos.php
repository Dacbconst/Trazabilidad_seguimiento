<?php
// Datos del "Resumen de Pagos" de una importación puntual (rebate real +
// visibilidad, juntos por cliente) — ver liquidacion_calcular_resumen_pagos()
// en includes/liquidacion_import.php para la lógica real.
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

$importacionId = (int) ($_GET['importacion_id'] ?? 0);
if ($importacionId <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Importación inválida.']);
	exit;
}

$stmt = $mysqli->prepare('SELECT canal, anio, mes_inicio, mes_fin, nombre_archivo FROM repositorio_liquidacion_importaciones WHERE id = ?');
$stmt->bind_param('i', $importacionId);
$stmt->execute();
$importacion = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$importacion) {
	echo json_encode(['ok' => false, 'message' => 'Importación no encontrada.']);
	exit;
}

$filas = liquidacion_calcular_resumen_pagos($mysqli, $importacionId);

echo json_encode(['ok' => true, 'importacion' => $importacion, 'filas' => $filas]);
?>
