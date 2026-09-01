<?php
// Filas sin resolver de una importación (las 2 tablas juntas), para "Pendientes
// de Asignar" — trae candidatos de pos_id y, si el cliente resuelve único pero
// hay 2+ Actas superpuestas en el período, también las Actas candidatas.
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

// Recalcula candidatos de pos_id al vuelo (no se guardaron, solo el resultado del match).
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

	// Cliente sin ambigüedad: recalcular si el 2do paso (pos_id -> Acta) también resuelve.
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
