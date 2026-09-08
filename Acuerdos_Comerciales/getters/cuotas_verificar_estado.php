<?php
// Chequeo antes de guardar, solo lectura: resuelve pos_id/sector por fila y dice si sería nueva, actualizaría algo, o ya generó una Acta real.
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
$canal     = ($body['canal'] ?? '') === 'distribuidor' ? 'distribuidor' : 'directo';

if (!$filas || $trimestre < 1 || $trimestre > 4 || $anio <= 0) {
	responder(false, 'Parámetros inválidos.');
}

// Cachea dentro de esta verificación: resolverSectorReal()/resolverPosIdCliente() escanean sin índice útil y esto corre en cada cambio de Año.
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

	// Mismo dato que avisará cuotas_guardar.php al guardar, expuesto acá para que el badge de previsualización lo muestre antes de confirmar.
	if (!array_key_exists($sector, $cacheSector)) {
		$cacheSector[$sector] = resolverSectorReal($mysqli, $sector);
	}
	$sectorCrudoMatch = $cacheSector[$sector];
	$sectorResuelto = $sectorCrudoMatch ?: $sector;
	$sectorInterpretado = $sectorCrudoMatch !== null && $sectorCrudoMatch !== $sector;
	$sectorSinResolver = $sectorCrudoMatch === null;

	$clavePos = $clienteExcel.'|'.$cediExcel;
	if (!array_key_exists($clavePos, $cachePosId)) {
		$plan = repositorio_normalizar_texto($fila['plan'] ?? '');
		$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel, $canal, $plan);
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
