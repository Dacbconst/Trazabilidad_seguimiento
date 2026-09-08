<?php
// Inverso de "Eliminar" (borrado lógico, estado='descartada'): vuelve a 'pendiente_uso' o 'pendiente_match' según si el pos_id ya estaba resuelto.
// Nunca reactiva una fila 'usada', a propósito.
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

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
	responder(false, 'Falta el id de la fila.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$stmt = $mysqli->prepare(
	"UPDATE repositorio_cuota_cliente
	 SET estado = IF(pos_id IS NOT NULL, 'pendiente_uso', 'pendiente_match'), actualizado_por = ?
	 WHERE id = ? AND estado = 'descartada'"
);
if (!$stmt) {
	responder(false, 'El Repositorio de Cuotas todavía no existe en la base.');
}
$stmt->bind_param('ii', $usuarioSesion, $id);
$stmt->execute();
$ok = $stmt->affected_rows > 0;
$stmt->close();

responder($ok, $ok ? 'Reactivada correctamente.' : 'No se pudo reactivar (¿ya estaba activa?).');
?>
