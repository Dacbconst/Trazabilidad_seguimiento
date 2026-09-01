<?php
// TEMPORAL: retrocede fecha_generacion de un Acta propia para probar el vencimiento sin esperar 20 días.
// Borrar este archivo + su botón en Historial cuando termine la prueba.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

$acuerdoId = (int) ($_POST['id'] ?? 0);
$modo      = $_POST['modo'] ?? '';
$usuarioId = $_SESSION['user_id'] ?? null;

// $dias whitelisteado a propósito, no un número libre desde el cliente.
$diasPorModo = ['aviso' => 16, 'vencido' => 21, 'revertir' => 0];
if ($acuerdoId <= 0 || !isset($diasPorModo[$modo])) {
	echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos.']);
	exit;
}
$dias = $diasPorModo[$modo];

// Mismo criterio de propiedad que el resto de Historial: solo el dueño.
$stmt = $mysqli->prepare('SELECT creado_por, estado FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioId) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo no encontrado.']);
	exit;
}
// "revertir" además acepta 'vencido'; "aviso"/"vencido" solo sobre algo vigente.
$estadosPermitidos = $modo === 'revertir' ? ['generado', 'enviado', 'vencido'] : ['generado', 'enviado'];
if (!in_array($fila['estado'], $estadosPermitidos, true)) {
	echo json_encode(['ok' => false, 'message' => 'Este Acuerdo no está en un estado válido para esta prueba.']);
	exit;
}

if ($modo === 'revertir') {
	// Deja el Acuerdo como recién generado hoy; no recupera la fecha original.
	$stmt = $mysqli->prepare("UPDATE repositorio_acuerdos SET fecha_generacion = CURDATE(), estado = 'generado' WHERE id = ?");
	$stmt->bind_param('i', $acuerdoId);
} else {
	$stmt = $mysqli->prepare('UPDATE repositorio_acuerdos SET fecha_generacion = DATE_SUB(CURDATE(), INTERVAL ? DAY) WHERE id = ?');
	if ($stmt) $stmt->bind_param('ii', $dias, $acuerdoId);
}
if (!$stmt) {
	echo json_encode(['ok' => false, 'message' => 'No se pudo simular (revisar CLAUDE.md).']);
	exit;
}
$ok = $stmt->execute();
$stmt->close();

$mensajes = [
	'vencido'  => 'Fecha adelantada 21 días — recargá Historial: el Acta debería desaparecer y bloquearse.',
	'aviso'    => 'Fecha adelantada 16 días (quedan 4 de plazo) — revisá la campanita del header.',
	'revertir' => 'Acuerdo restaurado a "generado" con fecha de hoy.',
];
echo json_encode(['ok' => (bool) $ok, 'message' => $ok ? $mensajes[$modo] : 'No se pudo simular.']);
?>
