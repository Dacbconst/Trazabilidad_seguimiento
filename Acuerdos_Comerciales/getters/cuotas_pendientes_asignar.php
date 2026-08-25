<?php
// Cola de resolución manual del Repositorio de Cuotas — filas donde
// resolverPosIdCliente() no encontró exactamente un cliente (ver
// getters/cuotas_guardar.php). Mismo concepto visual que "Pendientes de
// Asignar" de Liquidación: se muestran junto con candidatos sugeridos (match
// por nombre, sin filtrar por CEDI) para que el superdesarrollador elija a
// mano en vez de tipear un pos_id a ciegas.
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
