<?php
// Resuelve a mano una fila de "Pendientes de Asignar": accion=matchear (pos_id
// elegido -> busca el acuerdo_id por solape de período) o accion=sin_acta
// (estado final, para históricos que nunca van a tener Acta digital). Si viene
// $_POST['acuerdo_id'] (caso de 2+ Actas candidatas), se valida contra la
// lista real recalculada, nunca se confía en el id tal cual.
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
		// Elegido a mano, revalidado contra los candidatos reales.
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
