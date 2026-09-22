<?php
// Marca la firma ya subida como revisada y correcta — Seguimiento de Equipo, ver includes/functions.php resumen_seguimiento_equipo() y assets/js/seguimiento.js abrirFirmaSoloLectura(). No toca el archivo, solo registra quién y cuándo la validó.
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

$acuerdoId = (int) ($_POST['id'] ?? 0);
$usuarioId = $_SESSION['user_id'] ?? null;
if ($acuerdoId <= 0) {
	responder(false, 'Acuerdo inválido.');
}

$stmt = $mysqli->prepare('SELECT acta_firmada_azure_path FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$fila) {
	responder(false, 'El Acuerdo ya no existe.');
}
if (!$fila['acta_firmada_azure_path']) {
	responder(false, 'Este Acuerdo todavía no tiene una firma subida para validar.');
}

$stmt = $mysqli->prepare(
	'UPDATE repositorio_acuerdos SET firma_validada_en = NOW(), firma_validada_por = ? WHERE id = ?'
);
$stmt->bind_param('ii', $usuarioId, $acuerdoId);
$stmt->execute();
$stmt->close();

responder(true, 'Firma validada correctamente.');
?>
