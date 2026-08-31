<?php
// Chequeo ANTES de guardar (2026-08-25, pedido explícito: "no quiero
// enterarme recién después de guardar qué era nuevo y qué modifiqué") —
// para cada fila de la previsualización, resuelve pos_id/sector (mismo
// criterio que cuotas_guardar.php, pero de SOLO LECTURA, nunca escribe) y
// dice si esa fila sería nueva, actualizaría algo que ya existe, o no se
// puede tocar porque ya generó una Acta real. Se llama cada vez que el
// superdesarrollador cambia el Año en la previsualización (el trimestre ya
// se sabe del Excel, pero el año recién se tipea ahí).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/repositorio_import.php';
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

$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);

if (!$filas || $trimestre < 1 || $trimestre > 4 || $anio <= 0) {
	responder(false, 'Parámetros inválidos.');
}

// Cache dentro de esta misma verificación (2026-08-30, mismo bug de
// rendimiento que cuotas_guardar.php — ver la nota completa ahí):
// resolverSectorReal()/resolverPosIdCliente() escanean tablas sin índice
// útil, y este endpoint corre TODAVÍA más seguido que el guardado real
// (cada vez que se cambia el Año en la previsualización). El mismo texto
// de Sector/Cliente da siempre el mismo resultado dentro de una sola
// verificación, así que cachear es seguro.
$cacheSector = [];
$cachePosId  = [];
$stmtExistente = $mysqli->prepare(
	'SELECT estado FROM repositorio_cuota_cliente WHERE pos_id = ? AND sector = ? AND trimestre = ? AND anio = ? LIMIT 1'
);

$estados = [];
foreach ($filas as $fila) {
	$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
	$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
	$sector       = repositorio_normalizar_texto($fila['sector'] ?? '');
	if ($clienteExcel === '' || $sector === '') {
		$estados[] = ['estado' => 'invalido'];
		continue;
	}

	// Mismo dato que va a avisar cuotas_guardar.php al guardar de verdad —
	// se expone ACÁ también para que el badge de la previsualización ya lo
	// muestre ANTES de confirmar (2026-08-25, pedido explícito: no
	// enterarse recién en el aviso rojo de después de guardar).
	if (!array_key_exists($sector, $cacheSector)) {
		$cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
	}
	$sectorCrudoMatch = $cacheSector[$sector];
	$sectorResuelto = $sectorCrudoMatch ?: $sector;
	$sectorInterpretado = $sectorCrudoMatch !== null && $sectorCrudoMatch !== $sector;
	$sectorSinResolver = $sectorCrudoMatch === null;

	$clavePos = $clienteExcel.'|'.$cediExcel;
	if (!array_key_exists($clavePos, $cachePosId)) {
		$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
	}
	$posId = $cachePosId[$clavePos];

	if (!$posId) {
		$estados[] = [
			'estado' => 'sin_cliente', 'sector_resuelto' => $sectorResuelto,
			'sector_interpretado' => $sectorInterpretado, 'sector_sin_resolver' => $sectorSinResolver,
		];
		continue;
	}

	$existente = null;
	if ($stmtExistente) {
		$stmtExistente->bind_param('ssii', $posId, $sectorResuelto, $trimestre, $anio);
		$stmtExistente->execute();
		$existente = $stmtExistente->get_result()->fetch_assoc();
	}

	if (!$existente) {
		$estado = 'nuevo';
	} elseif ($existente['estado'] === 'usada') {
		$estado = 'usada';
	} else {
		$estado = 'actualiza';
	}
	$estados[] = [
		'estado' => $estado, 'sector_resuelto' => $sectorResuelto,
		'sector_interpretado' => $sectorInterpretado, 'sector_sin_resolver' => $sectorSinResolver,
	];
}
if ($stmtExistente) $stmtExistente->close();

responder(true, 'ok', ['estados' => $estados]);
?>
