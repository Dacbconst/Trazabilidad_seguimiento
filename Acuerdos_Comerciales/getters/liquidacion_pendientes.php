<?php
// Filas de una importación que quedaron sin resolver (estado_match distinto
// de 'matcheado'), de las 2 tablas juntas, para la pantalla "Pendientes de
// Asignar". Para las que tienen candidatos ambiguos (más de un pos_id
// posible), se resuelve el pos_name de cada candidato para mostrarlo — el
// superdesarrollador elige a mano cuál es.
//
// Agregado 2026-08-20 — ambigüedad de ACTA (no de cliente): puede pasar que
// el nombre resuelva a UN SOLO pos_id (cliente sin ambigüedad) pero ese
// cliente tenga 2+ Actas que se solapan con el período+año de esta
// importación (ver liquidacion_candidatos_acuerdo_id() en
// includes/liquidacion_import.php) — antes de esto, esa fila quedaba
// mostrando "1 candidato" (se veía resuelta) pero en realidad estaba
// trabada en el segundo paso del match, y recién al intentar confirmarla
// salía un error sin ninguna forma de elegir cuál Acta es desde acá. Ahora,
// cuando el pos_id resuelve a 1 solo, se recalculan también las Actas
// candidatas y se devuelven (documento_no/fecha/estado) para que el
// frontend pueda mostrar un selector de Acta en vez de un callejón sin salida.
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

$importacionId = (int) ($_GET['importacion_id'] ?? 0);
if ($importacionId <= 0) {
	echo json_encode(['ok' => false, 'message' => 'Importación inválida.']);
	exit;
}

$stmtImp = $mysqli->prepare('SELECT canal, anio, mes_inicio, mes_fin FROM repositorio_liquidacion_importaciones WHERE id = ?');
$stmtImp->bind_param('i', $importacionId);
$stmtImp->execute();
$importacion = $stmtImp->get_result()->fetch_assoc();
$stmtImp->close();
if (!$importacion) {
	echo json_encode(['ok' => false, 'message' => 'Importación no encontrada.']);
	exit;
}
$canal = $importacion['canal'];

function liquidacion_filas_pendientes_de($mysqli, $tabla, $importacionId, $tipoLabel) {
	$stmt = $mysqli->prepare(
		"SELECT id, cedi_o_distribuidor, cliente_o_nombre, estado_match
		 FROM $tabla WHERE importacion_id = ? AND estado_match NOT IN ('matcheado', 'sin_acta')
		 ORDER BY cliente_o_nombre"
	);
	$stmt->bind_param('i', $importacionId);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	foreach ($filas as &$f) $f['tabla'] = $tipoLabel;
	return $filas;
}

$pendientes = array_merge(
	liquidacion_filas_pendientes_de($mysqli, 'repositorio_liquidacion_cuota_categoria', $importacionId, 'cuota_categoria'),
	liquidacion_filas_pendientes_de($mysqli, 'repositorio_liquidacion_visibilidad', $importacionId, 'visibilidad')
);

// Para cada fila, recalcular los candidatos de pos_id (no se guardaron en su
// momento, solo el resultado del match) y traer su pos_name para mostrarlos
// — es una consulta liviana, y esta pantalla no se usa con tanta frecuencia
// como para justificar guardar los candidatos aparte.
foreach ($pendientes as &$fila) {
	$posIds = liquidacion_candidatos_pos_id($mysqli, $canal, $fila['cedi_o_distribuidor'], $fila['cliente_o_nombre']);
	$candidatos = [];
	if ($posIds) {
		$placeholders = implode(',', array_fill(0, count($posIds), '?'));
		$stmt = $mysqli->prepare(
			"SELECT DISTINCT pos_id, pos_name, cedi, supervisor, tipo_distribuidor
			 FROM repositorio_locales_supervisores_cliente WHERE pos_id IN ($placeholders)"
		);
		$stmt->bind_param(str_repeat('s', count($posIds)), ...$posIds);
		$stmt->execute();
		$candidatos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$stmt->close();
	}
	$fila['candidatos'] = $candidatos;

	// Cliente sin ambigüedad (1 solo pos_id) — recalcular si el segundo paso
	// del match (pos_id -> Acta) también está resuelto, o si es ahí donde
	// está trabada la fila (ver nota arriba).
	$fila['pos_id_resuelto'] = null;
	$fila['actas_candidatas'] = [];
	if (count($posIds) === 1) {
		$fila['pos_id_resuelto'] = $posIds[0];
		$acuerdoIds = liquidacion_candidatos_acuerdo_id(
			$mysqli, $posIds[0], (int) $importacion['mes_inicio'], (int) $importacion['mes_fin'], (int) $importacion['anio']
		);
		if (count($acuerdoIds) > 1) {
			$placeholdersA = implode(',', array_fill(0, count($acuerdoIds), '?'));
			$stmtA = $mysqli->prepare(
				"SELECT id, documento_no, fecha_generacion, estado, created_at
				 FROM repositorio_acuerdos WHERE id IN ($placeholdersA) ORDER BY created_at DESC"
			);
			$stmtA->bind_param(str_repeat('i', count($acuerdoIds)), ...$acuerdoIds);
			$stmtA->execute();
			$fila['actas_candidatas'] = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
			$stmtA->close();
		}
	}
}

echo json_encode(['ok' => true, 'canal' => $canal, 'pendientes' => $pendientes]);
?>
