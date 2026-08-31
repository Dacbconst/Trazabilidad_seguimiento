<?php
// Elimina una fila de un repositorio.
//
// Rebate/Participación: BORRADO LÓGICO (2026-08-25, regla base — antes era
// DELETE físico real; mismo caso real que motivó el cambio de Cuotas más
// abajo: "si por error borro algo, ¿cómo lo recupero?"). "Eliminar" pasa a
// ser un UPDATE que llena `eliminado_en`/`eliminado_por` (columnas nuevas,
// ver datos/repositorios_schema.sql) — la fila sigue existiendo, sale del
// listado normal (listar_repositorio_rebate()/_participacion() en
// functions.php ya filtran `eliminado_en IS NULL`) y se puede reactivar
// desde "Eliminados" (getters/repositorio_eliminados.php +
// getters/repositorio_reactivar.php) filtrando por fecha si hace falta
// ubicar "qué se borró y cuándo".
//
// Cuotas es distinto en 2 sentidos (2026-08-25, pedido explícito del
// usuario tras un caso real: "si por error borro algo, ¿cómo lo recupero?"):
//   - Si ya está 'usada' (generó una Acta real) el borrado se BLOQUEA del
//     todo — corregir eso es trabajo de Historial (anular el Acuerdo), no
//     de este repositorio.
//   - Para el resto (pendiente_match/pendiente_uso) "Eliminar" pasó a ser
//     borrado LÓGICO (`estado='descartada'`, no `DELETE`) — recuperable con
//     "Reactivar" (getters/cuotas_reactivar.php), y queda `updated_at` para
//     poder filtrar/ubicar "qué se descartó y cuándo" si después hay que
//     deshacerlo. `descartada` ya se mostraba en la tabla con su propio
//     badge (ver assets/js/repositorios.js), esto solo cambia CÓMO se llega
//     a ese estado.
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

if (!in_array($tipo, ['rebate', 'participacion', 'cuotas'], true) || $id <= 0) {
	responder(false, 'Parámetros inválidos.');
}

$tablasPorTipo = [
	'rebate'        => 'repositorio_rebate_producto',
	'participacion' => 'repositorio_participacion_percha',
	'cuotas'        => 'repositorio_cuota_cliente',
];
$tabla = $tablasPorTipo[$tipo];

if ($tipo === 'cuotas') {
	$stmt = $mysqli->prepare("SELECT estado FROM repositorio_cuota_cliente WHERE id = ? LIMIT 1");
	if ($stmt) {
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$fila = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if ($fila && $fila['estado'] === 'usada') {
			responder(false, 'Esta fila ya generó una Acta. No se puede eliminar. Si el dato está mal, corregilo desde la Acta en Historial.');
		}
	}

	$usuarioSesion = $_SESSION['user_id'] ?? null;
	$stmt = $mysqli->prepare("UPDATE repositorio_cuota_cliente SET estado = 'descartada', actualizado_por = ? WHERE id = ?");
	if (!$stmt) {
		responder(false, 'El Repositorio de Cuotas todavía no está disponible. Avisa al equipo técnico.');
	}
	$stmt->bind_param('ii', $usuarioSesion, $id);
	$stmt->execute();
	$stmt->close();
	responder(true, 'Descartada correctamente. Se puede reactivar desde la tabla si hace falta.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$stmt = $mysqli->prepare("UPDATE $tabla SET eliminado_en = NOW(), eliminado_por = ? WHERE id = ? AND eliminado_en IS NULL");
if (!$stmt) {
	responder(false, 'El repositorio todavía no está disponible. Avisa al equipo técnico.');
}
$stmt->bind_param('ii', $usuarioSesion, $id);
$stmt->execute();
$stmt->close();

responder(true, 'Eliminado correctamente. Se puede reactivar desde "Eliminados" si hace falta.');
?>
