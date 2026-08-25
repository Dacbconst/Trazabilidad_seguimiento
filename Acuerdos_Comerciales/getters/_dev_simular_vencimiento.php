<?php
// TEMPORAL — SOLO PARA PROBAR "Vencimiento de firma" (2026-08-25).
// Retrocede fecha_generacion de UN Acta propia para simular el paso del
// tiempo, sin esperar 20 días reales — el usuario no tiene permiso de
// escritura en su cuenta personal de HeidiSQL (confirmado), así que esto
// vive acá para que lo dispare él mismo desde la UI (mismo mecanismo que
// ya usan eliminar_acuerdo.php/subir_acta_firmada.php: el backend de la
// app escribe con las credenciales de config.php, nunca Claude directo).
// Incluye un modo "revertir" para deshacer la simulación sobre la misma
// fila (sin esto, una vez probado "vencido" no habría forma de destestearlo
// sin permiso de escritura). Borrar este archivo + su botón en Historial
// (renderFilaHistorial()) + el handler de historial.js una vez terminada
// la prueba — ver CLAUDE.md, "Vencimiento de firma".
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

// $dias whitelisteado a propósito (no un número libre desde el cliente) —
// esto es una herramienta de prueba puntual, no un "set any date" genérico.
$diasPorModo = ['aviso' => 16, 'vencido' => 21, 'revertir' => 0];
if ($acuerdoId <= 0 || !isset($diasPorModo[$modo])) {
	echo json_encode(['ok' => false, 'message' => 'Parámetros inválidos.']);
	exit;
}
$dias = $diasPorModo[$modo];

// Mismo criterio de propiedad que el resto de acciones de Historial: nadie
// simula el vencimiento de un Acuerdo ajeno adivinando el id.
$stmt = $mysqli->prepare('SELECT creado_por, estado FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $acuerdoId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || (int) $fila['creado_por'] !== (int) $usuarioId) {
	echo json_encode(['ok' => false, 'message' => 'Acuerdo no encontrado.']);
	exit;
}
// "revertir" además acepta 'vencido' (para deshacer el propio efecto de
// esta herramienta) — "aviso"/"vencido" solo tienen sentido sobre algo
// todavía en pie, no ya vencido de antes.
$estadosPermitidos = $modo === 'revertir' ? ['generado', 'enviado', 'vencido'] : ['generado', 'enviado'];
if (!in_array($fila['estado'], $estadosPermitidos, true)) {
	echo json_encode(['ok' => false, 'message' => 'Este Acuerdo no está en un estado válido para esta prueba.']);
	exit;
}

if ($modo === 'revertir') {
	// Deja el Acuerdo como recién generado hoy — no se puede recuperar la
	// fecha_generacion original exacta, pero para efectos de seguir
	// probando (o de dejar de "vencerlo") alcanza con esto.
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
