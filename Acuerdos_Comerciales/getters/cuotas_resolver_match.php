<?php
// Resuelve a mano una fila de "Pendientes de Asignar" del Repositorio de
// Cuotas (mismo espíritu que getters/liquidacion_resolver_match.php):
//   - accion=matchear (default): recibe el pos_id que el superdesarrollador
//     eligió (de los candidatos sugeridos o de una búsqueda libre) y lo
//     asigna a la fila, que pasa a estado 'pendiente_uso' (lista para que el
//     asesor la vea en "Actas Precargadas").
//   - accion=descartar: la fila no corresponde a ningún cliente real de la
//     base (ej. error de tipeo en el Excel de JW) — se marca 'descartada',
//     un estado final que no vuelve a aparecer en la cola.
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

$id     = (int) ($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? 'matchear';
$posId  = trim($_POST['pos_id'] ?? '');

if ($id <= 0) {
	responder(false, 'Falta el id de la fila.');
}
if (!in_array($accion, ['matchear', 'descartar'], true)) {
	responder(false, 'Acción inválida.');
}
if ($accion === 'matchear' && $posId === '') {
	responder(false, 'Falta el pos_id elegido.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;

if ($accion === 'descartar') {
	$stmt = $mysqli->prepare(
		"UPDATE repositorio_cuota_cliente SET estado = 'descartada', actualizado_por = ? WHERE id = ? AND estado = 'pendiente_match'"
	);
	$stmt->bind_param('ii', $usuarioSesion, $id);
	$ok = $stmt->execute();
	$stmt->close();
	responder((bool) $ok, $ok ? 'Marcada como descartada.' : 'No se pudo guardar.');
}

// El pos_id elegido nunca se confía tal cual venga del cliente — se valida
// que exista de verdad en el maestro antes de asignarlo.
$stmt = $mysqli->prepare('SELECT 1 FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? LIMIT 1');
$stmt->bind_param('s', $posId);
$stmt->execute();
$existe = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$existe) {
	responder(false, 'Ese pos_id no existe en el maestro de clientes.');
}

$stmt = $mysqli->prepare(
	"UPDATE repositorio_cuota_cliente SET pos_id = ?, estado = 'pendiente_uso', actualizado_por = ? WHERE id = ? AND estado = 'pendiente_match'"
);
$stmt->bind_param('sii', $posId, $usuarioSesion, $id);
$ok = $stmt->execute();
$stmt->close();

// La UNIQUE (pos_id, sector, trimestre, anio) puede chocar si ya existe una
// fila resuelta para ese mismo cliente+categoría+período (ej. Michelle
// corrigió y volvió a subir el mismo trimestre después de que esta fila ya
// había quedado en la cola por ambigüedad) — se avisa en vez de fallar mudo,
// el superdesarrollador decide cuál de las dos filas conservar a mano
// (borrando la vieja desde la tabla principal del repositorio).
if (!$ok && $mysqli->errno === 1062) {
	responder(false, 'Ya existe una cuota guardada para ese cliente, categoría y período. Borrá la fila vieja en la tabla de Cuotas antes de asignar esta.');
}

responder((bool) $ok, $ok ? 'Cliente asignado correctamente.' : 'No se pudo guardar.');
?>
