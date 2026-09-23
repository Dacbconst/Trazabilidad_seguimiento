<?php
// Detalle de un Acta (droplist de Resumen de Negociación): qué tablas tiene y valores de cada una. Mismo chequeo de rol que el resto del módulo.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$id = (int) ($_GET['id'] ?? 0);
$detalle = obtener_negociacion_detalle_acuerdo($mysqli, $id);
if ($detalle === null) {
	echo json_encode(['ok' => false, 'message' => 'Acta no encontrada.']);
	exit;
}

echo json_encode(['ok' => true] + $detalle);
?>
