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
$cacheCediReal = [];
$stmtExistente = $mysqli->prepare(
	'SELECT estado FROM repositorio_cuota_cliente WHERE pos_id = ? AND sector = ? AND trimestre = ? AND anio = ? LIMIT 1'
);
// CEDI/Ciudad real del cliente ya identificado (2026-09-21, pedido explícito: "si en base dice Guayaquil pero el Excel me pone Quito, avisar antes de guardar") — MIN() por si el pos_id se repite en el maestro.
$stmtCediReal = $mysqli->prepare(
	'SELECT MIN(cedi) AS cedi FROM repositorio_locales_supervisores_cliente WHERE pos_id = ?'
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
	// "Se asigna a" (2026-09-17): quién va a recibir esta Acta Precargada, resuelto ANTES
	// de guardar — mismo criterio que usuarioIdDeCuota() (CEDI del Excel gana, maestro
	// como respaldo), ver resolverNombreAsignadoCuota(). pos_id se manda también para que
	// la previsualización pueda agrupar visualmente las filas de un mismo cliente.
	// tiene_cuenta distingue "cliente identificado, supervisor real conocido, pero sin
	// cuenta de usuario todavía" de "no se pudo identificar nada" (pedido explícito).
	$asignado = resolverNombreAsignadoCuota($mysqli, $posId, $cediExcel);

	if (!array_key_exists($posId, $cacheCediReal)) {
		$cediReal = null;
		if ($stmtCediReal) {
			$stmtCediReal->bind_param('s', $posId);
			$stmtCediReal->execute();
			$filaCedi = $stmtCediReal->get_result()->fetch_assoc();
			$cediReal = $filaCedi ? $filaCedi['cedi'] : null;
		}
		$cacheCediReal[$posId] = $cediReal;
	}

	$estados[] = [
		'estado' => $estado, 'sector_resuelto' => $sectorResuelto,
		'sector_interpretado' => $sectorInterpretado, 'sector_sin_resolver' => $sectorSinResolver,
		'pos_id' => $posId, 'asignado_a' => $asignado['nombre'], 'tiene_cuenta' => $asignado['tiene_cuenta'],
		'cedi_real' => $cacheCediReal[$posId],
	];
}
if ($stmtExistente) $stmtExistente->close();
if ($stmtCediReal) $stmtCediReal->close();

responder(true, 'ok', ['estados' => $estados]);
?>
