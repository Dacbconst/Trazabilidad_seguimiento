<?php
// Cola de resolución manual de "Acuerdo Completo" — mismo patrón que cuotas_pendientes_asignar.php.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

echo json_encode(['ok' => true, 'filas' => listar_repositorio_acuerdo_completo_pendientes_match($mysqli)]);
?>
