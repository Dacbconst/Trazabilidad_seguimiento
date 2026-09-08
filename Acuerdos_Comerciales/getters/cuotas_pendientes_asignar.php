<?php
// Cola de resolución manual: filas donde resolverPosIdCliente() no encontró exactamente un cliente. Se muestran con candidatos sugeridos por nombre.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

echo json_encode(['ok' => true, 'filas' => listar_repositorio_cuotas_pendientes_match($mysqli)]);
?>
