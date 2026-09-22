<?php
// Rechaza la firma subida — el asesor debe volver a subir una nueva. Borra el archivo actual (acta_firmada_azure_path vuelve a NULL) a propósito: así el Acuerdo reaparece como "pendiente de firma" en Historial/campanita, reusando ese mecanismo de notificación existente en vez de construir uno aparte. Ver includes/functions.php resumen_seguimiento_equipo() y assets/js/seguimiento.js abrirFirmaSoloLectura().
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

$body      = json_decode(file_get_contents('php://input'), true);
$acuerdoId = (int) ($body['id'] ?? 0);
$motivo    = trim($body['motivo'] ?? '');
$usuarioId = $_SESSION['user_id'] ?? null;

if ($acuerdoId <= 0) {
	responder(false, 'Acuerdo inválido.');
}
if ($motivo === '') {
	responder(false, 'Indica el motivo del rechazo.');
}

$stmt = $mysqli->prepare('SELECT acta_firmada_azure_path, documento_no FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$fila) {
	responder(false, 'El Acuerdo ya no existe.');
}
if (!$fila['acta_firmada_azure_path']) {
	responder(false, 'Este Acuerdo todavía no tiene una firma subida para rechazar.');
}

$stmt = $mysqli->prepare(
	'UPDATE repositorio_acuerdos
	 SET acta_firmada_azure_path = NULL, acta_firmada_mime = NULL,
	     firma_validada_en = NULL, firma_validada_por = NULL,
	     firma_rechazada_en = NOW(), firma_rechazada_por = ?, firma_rechazada_motivo = ?
	 WHERE id = ?'
);
$stmt->bind_param('isi', $usuarioId, $motivo, $acuerdoId);
$stmt->execute();
$stmt->close();

responder(true, 'Firma rechazada. El Acuerdo "'.$fila['documento_no'].'" vuelve a quedar pendiente de firma para el asesor.');
?>
