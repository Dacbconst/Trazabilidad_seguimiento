<?php
// Reactiva una fila borrada lógicamente de Rebate o Participación de Percha
// (limpia eliminado_en/eliminado_por) — ver repositorio_eliminar.php y la
// nota de borrado lógico en datos/repositorios_schema.sql. Solo
// superdesarrollador, mismo criterio que el resto de Repositorios.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message) {
	echo json_encode(['ok' => $ok, 'message' => $message]);
	exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$tipo = $body['tipo'] ?? '';
$id   = (int) ($body['id'] ?? 0);

if (!in_array($tipo, ['rebate', 'participacion'], true) || $id <= 0) {
	responder(false, 'Parámetros inválidos.');
}

$tablasPorTipo = [
	'rebate'        => 'repositorio_rebate_producto',
	'participacion' => 'repositorio_participacion_percha',
];
$tabla = $tablasPorTipo[$tipo];

$stmt = $mysqli->prepare("UPDATE $tabla SET eliminado_en = NULL, eliminado_por = NULL WHERE id = ? AND eliminado_en IS NOT NULL");
if (!$stmt) {
	responder(false, 'El repositorio todavía no está disponible. Avisa al equipo técnico.');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$reactivada = $stmt->affected_rows > 0;
$stmt->close();

responder($reactivada, $reactivada ? 'Reactivada correctamente.' : 'No se encontró esa fila borrada (¿ya se había reactivado?).');
?>
