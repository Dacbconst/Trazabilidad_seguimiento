<?php
// Borrado lógico de una fila de Cumplimiento de Cuota (una categoría de un
// cliente/período puntual) — nunca DELETE físico, mismo patrón que
// Rebate/Participación. Recuperar: volver a subir el mismo Excel del mismo
// trimestre/año limpia eliminado_en/eliminado_por solo (ver
// getters/cumplimiento_guardar.php) — no hace falta una pantalla de
// "Eliminados"/Reactivar aparte para esto.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

// Ver nota completa en cumplimiento_guardar.php: bufferea cualquier
// warning/notice de PHP para que nunca se mezcle con el JSON de respuesta.
ob_start();

function responder($ok, $message) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(['ok' => $ok, 'message' => $message]);
	exit;
}
set_exception_handler(function ($e) { responder(false, 'No se pudo eliminar: '.$e->getMessage()); });

$body = json_decode(file_get_contents('php://input'), true);
$id = (int) ($body['id'] ?? 0);
if ($id <= 0) {
	responder(false, 'Falta indicar qué fila eliminar.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$stmt = $mysqli->prepare(
	'UPDATE repositorio_cumplimiento_cuota SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ? AND eliminado_en IS NULL'
);
if (!$stmt) {
	responder(false, 'No se pudo eliminar. Avisá al equipo técnico.');
}
$stmt->bind_param('ii', $usuarioSesion, $id);
$stmt->execute();
$afectadas = $stmt->affected_rows;
$stmt->close();

if ($afectadas === 0) {
	responder(false, 'Esa fila ya no existe o ya estaba eliminada.');
}
responder(true, 'Fila eliminada.');
?>
