<?php
// Resumen visual del Repositorio "Acuerdo Completo" — mismo espíritu que cuotas_resumen.php, ver includes/functions.php resumen_acuerdo_completo().
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

echo json_encode(['ok' => true] + resumen_acuerdo_completo($mysqli));
?>
