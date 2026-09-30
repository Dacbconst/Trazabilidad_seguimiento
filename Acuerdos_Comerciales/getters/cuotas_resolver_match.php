<?php
// Resuelve a mano una fila de "Pendientes de Asignar": accion=matchear asigna el pos_id elegido (pasa a 'pendiente_uso'); accion=descartar la marca 'descartada' (estado final, no vuelve a la cola).
require_once __DIR__.'/../includes/functions.php';
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

// ids (2026-09-30): lista separada por comas — 1 Acta = todas las categorías de un mismo cliente, se resuelven juntas. "id" (singular) se mantiene como alias por compatibilidad.
$idsCrudo = $_POST['ids'] ?? $_POST['id'] ?? '';
$ids      = array_values(array_filter(array_map('intval', explode(',', (string) $idsCrudo)), fn($v) => $v > 0));
$accion   = $_POST['accion'] ?? 'matchear';
$posId    = trim($_POST['pos_id'] ?? '');

if (!$ids) {
	responder(false, 'Falta el id de la fila.');
}
if (!in_array($accion, ['matchear', 'descartar'], true)) {
	responder(false, 'Acción inválida.');
}
if ($accion === 'matchear' && $posId === '') {
	responder(false, 'Falta el pos_id elegido.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$marcadores = implode(',', array_fill(0, count($ids), '?'));

if ($accion === 'descartar') {
	$stmt = $mysqli->prepare(
		"UPDATE repositorio_cuota_cliente SET estado = 'descartada', actualizado_por = ? WHERE id IN ($marcadores) AND estado = 'pendiente_match'"
	);
	$stmt->bind_param('i'.str_repeat('i', count($ids)), $usuarioSesion, ...$ids);
	$ok = $stmt->execute();
	$afectadas = $stmt->affected_rows;
	$stmt->close();
	responder((bool) $ok, $ok ? "$afectadas categoría(s) descartada(s)." : 'No se pudo guardar.');
}

// Se valida que el pos_id exista de verdad en el maestro antes de asignarlo.
$stmt = $mysqli->prepare('SELECT 1 FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1');
$stmt->bind_param('s', $posId);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$existe) {
	responder(false, 'Ese pos_id no existe en el maestro de clientes.');
}

$stmt = $mysqli->prepare(
	"UPDATE repositorio_cuota_cliente SET pos_id = ?, estado = 'pendiente_uso', actualizado_por = ? WHERE id IN ($marcadores) AND estado = 'pendiente_match'"
);
$stmt->bind_param('si'.str_repeat('i', count($ids)), $posId, $usuarioSesion, ...$ids);
$ok = $stmt->execute();
$afectadas = $stmt->affected_rows;
$stmt->close();

// La UNIQUE (pos_id, sector, trimestre, anio) puede chocar si ya existe una fila resuelta para el mismo cliente+categoría+período — se avisa en vez de fallar mudo.
if (!$ok && $mysqli->errno === 1062) {
	responder(false, 'Ya existe una cuota guardada para ese cliente, categoría y período. Borrá la fila vieja en la tabla de Cuotas antes de asignar esta.');
}

responder((bool) $ok, $ok ? "$afectadas categoría(s) asignada(s) correctamente." : 'No se pudo guardar.');
?>
