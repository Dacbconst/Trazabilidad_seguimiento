<?php
// Resumen para superdesarrollador: conteo de Acuerdos por tipo negociado (Rebate/Cabeceras/Rumas/Perchas), por usuario.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$trimestre = (int) ($_GET['trimestre'] ?? 0);
$anio      = (int) ($_GET['anio'] ?? 0);

echo json_encode(['ok' => true] + resumen_negociacion_equipo($mysqli, $trimestre, $anio));
?>
