<?php
// Devuelve cabecera + líneas de un acuerdo ya generado, para el detalle/Acta
// imprimible que se abre desde Historial ("Ver Detalles" / "Descargar PDF").
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$id      = (int) ($_GET['id'] ?? 0);
$detalle = $id > 0 ? obtener_acuerdo_detalle($mysqli, $id) : null;

if (!$detalle) {
	http_response_code(404);
	echo json_encode(['ok' => false, 'message' => 'Acuerdo no encontrado.']);
	exit;
}

echo json_encode(['ok' => true, 'acuerdo' => $detalle]);
?>
