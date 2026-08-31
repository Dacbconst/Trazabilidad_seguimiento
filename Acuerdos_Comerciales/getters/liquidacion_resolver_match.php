<?php
// Resuelve a mano una fila de "Pendientes de Asignar", de dos formas:
//   - accion=matchear (default): recibe el pos_id que el superdesarrollador
//     eligió (de los candidatos sugeridos o de una búsqueda libre) y busca
//     el acuerdo_id correspondiente (mismo criterio de solape de
//     mes_inicio/mes_fin+año que el match automático, ver
//     liquidacion_candidatos_acuerdo_id() en includes/liquidacion_import.php).
//   - accion=sin_acta: marca la fila como "confirmado que no tiene Acta en
//     el sistema" — para datos históricos de antes de que existiera esta
//     plataforma (JW va a subir liquidaciones viejas que nunca van a poder
//     vincularse a una Acta digital, porque esa Acta nunca se creó acá).
//     Es un estado FINAL, no un "sin_match" que sigue esperando resolución.
//
// Agregado 2026-08-20 — ambigüedad de ACTA (mismo cliente, 2+ Actas cuyo
// período+año se solapan, ej. dos Actas generadas para el mismo lugar en el
// mismo trimestre): antes esto era un callejón sin salida (error fijo "revisar
// en Historial", sin forma de resolverlo desde acá). Ahora, si viene
// $_POST['acuerdo_id'], y ESE id está entre los candidatos legítimos
// recalculados para ese pos_id+período+año (nunca se confía en el id tal
// cual venga del cliente, siempre se valida contra la lista real), se guarda
// directo sin volver a chocar con la ambigüedad — es lo que arma
// getters/liquidacion_pendientes.php cuando detecta este caso y
// assets/js/liquidacion.js cuando el usuario hace click en la Acta correcta.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/liquidacion_import.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$tabla      = $_POST['tabla'] ?? '';
$id         = (int) ($_POST['id'] ?? 0);
$accion     = $_POST['accion'] ?? 'matchear';
$posId      = trim($_POST['pos_id'] ?? '');
$acuerdoIdElegido = (int) ($_POST['acuerdo_id'] ?? 0);

$tablasPermitidas = [
	'cuota_categoria' => 'repositorio_liquidacion_cuota_categoria',
	'visibilidad'      => 'repositorio_liquidacion_visibilidad',
];
if (!isset($tablasPermitidas[$tabla])) {
	responder(false, 'Tabla inválida.');
}
if ($id <= 0) {
	responder(false, 'Falta el id de la fila.');
}
if (!in_array($accion, ['matchear', 'sin_acta'], true)) {
	responder(false, 'Acción inválida.');
}
if ($accion === 'matchear' && $posId === '') {
	responder(false, 'Falta el pos_id elegido.');
}
$nombreTabla = $tablasPermitidas[$tabla];

$stmt = $mysqli->prepare("SELECT importacion_id FROM $nombreTabla WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$fila) {
	responder(false, 'La fila no existe.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$acuerdoId = null;

if ($accion === 'sin_acta') {
	$estadoNuevo = 'sin_acta';
} else {
	$stmt = $mysqli->prepare('SELECT mes_inicio, mes_fin, anio FROM repositorio_liquidacion_importaciones WHERE id = ? LIMIT 1');
	$stmt->bind_param('i', $fila['importacion_id']);
	$stmt->execute();
	$importacion = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$importacion) {
		responder(false, 'No se encontró la importación de esta fila.');
	}

	$acuerdoIds = liquidacion_candidatos_acuerdo_id($mysqli, $posId, (int) $importacion['mes_inicio'], (int) $importacion['mes_fin'], (int) $importacion['anio']);
	if (count($acuerdoIds) === 0) {
		responder(false, 'Ese pos_id no tiene ningún Acta generada para el período de esta importación — no se puede vincular. Si es un dato histórico, usá "No tiene Acta" en vez de buscar un pos_id.');
	}
	if (count($acuerdoIds) === 1) {
		$acuerdoId = $acuerdoIds[0];
	} elseif ($acuerdoIdElegido > 0 && in_array($acuerdoIdElegido, $acuerdoIds, true)) {
		// El cliente eligió a mano cuál Acta es (ver liquidacion_pendientes.php,
		// que arma esta lista de candidatos) — se valida contra los candidatos
		// reales de nuevo acá, nunca se confía en el id tal cual venga del POST.
		$acuerdoId = $acuerdoIdElegido;
	} else {
		responder(false, 'Ese cliente tiene más de una Acta para el período — elige cuál es de la lista.', ['acuerdo_ids_candidatos' => $acuerdoIds]);
	}
	$estadoNuevo = 'matcheado';
}

$stmt = $mysqli->prepare(
	"UPDATE $nombreTabla SET acuerdo_id = ?, estado_match = ?, matcheado_por = ?, matcheado_en = NOW() WHERE id = ?"
);
$stmt->bind_param('isii', $acuerdoId, $estadoNuevo, $usuarioSesion, $id);
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
	$stmt = $mysqli->prepare(
		"UPDATE repositorio_liquidacion_importaciones SET filas_pendientes = GREATEST(0, filas_pendientes - 1) WHERE id = ?"
	);
	$stmt->bind_param('i', $fila['importacion_id']);
	$stmt->execute();
	$stmt->close();
}

responder(
	(bool) $ok,
	$ok ? ($accion === 'sin_acta' ? 'Marcado como sin Acta (histórico).' : 'Vinculado correctamente.') : 'No se pudo guardar.',
	['acuerdo_id' => $acuerdoId]
);
?>
