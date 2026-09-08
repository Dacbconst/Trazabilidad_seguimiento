<?php
// Cabecera + líneas de un borrador, para "Continuar editando". Reusa obtener_acuerdo_detalle() (mismo shape que generar_acta_pdf.php).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$acuerdoId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$detalle   = $acuerdoId > 0 ? obtener_acuerdo_detalle($mysqli, $acuerdoId) : null;

// Solo quien creó el borrador puede reabrirlo (scoping por creado_por, igual que Historial), aunque adivine el id por la URL.
$usuarioSesion = $_SESSION['user_id'] ?? null;
if (!$detalle || $detalle['estado'] !== 'borrador' || (int) $detalle['creado_por'] !== (int) $usuarioSesion) {
	http_response_code(404);
	echo json_encode(['ok' => false, 'message' => 'Borrador no encontrado.']);
	exit;
}

echo json_encode(['ok' => true, 'acuerdo' => $detalle]);
?>
