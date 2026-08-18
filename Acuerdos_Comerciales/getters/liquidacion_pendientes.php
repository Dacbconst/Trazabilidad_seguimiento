<?php
// Filas de una importación que quedaron sin resolver (estado_match distinto
// de 'matcheado'), de las 2 tablas juntas, para la pantalla "Pendientes de
// Asignar". Para las que tienen candidatos ambiguos (más de un pos_id
// posible), se resuelve el pos_name de cada candidato para mostrarlo — el
// superdesarrollador elige a mano cuál es.
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
}

echo json_encode(['ok' => true, 'canal' => $canal, 'pendientes' => $pendientes]);
?>
