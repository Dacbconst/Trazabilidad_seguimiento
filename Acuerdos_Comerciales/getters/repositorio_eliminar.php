<?php
// Elimina una fila de un repositorio. A diferencia de repositorio_acuerdos
// (soft-delete, es un documento de negocio), acá es un DELETE físico real —
// esto es un catálogo de referencia, no un registro histórico que deba
// conservarse; borrar una fila mala no tiene ningún efecto retroactivo sobre
// Actas ya generadas (el rebate/participación se copia al valor tipeado en
// el momento, nunca queda "enlazado" al repositorio).
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

$tabla = $tipo === 'rebate' ? 'repositorio_rebate_producto' : 'repositorio_participacion_percha';
$stmt = $mysqli->prepare("DELETE FROM $tabla WHERE id = ?");
if (!$stmt) {
	responder(false, 'El repositorio todavía no existe en la base (falta correr datos/repositorios_schema.sql).');
}
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

responder(true, 'Eliminado correctamente.');
?>
