<?php
// Resumen para superdesarrollador: stats globales + conteos por usuario. Única pantalla que muestra Actas de todos, reforzar el chequeo de rol acá (no alcanza con ocultar el módulo del sidebar para otros roles).
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

echo json_encode(['ok' => true] + resumen_seguimiento_equipo($mysqli, $trimestre, $anio));
?>
