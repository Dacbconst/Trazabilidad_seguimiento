<?php
// Chequeo en vivo (solo lectura) antes de confirmar el guardado: resuelve pos_id/sector por fila y dice qué va a pasar (Nuevo/Actualiza/Sin cambios).
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/repositorio_import.php'; // repositorio_normalizar_texto()
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

// Bufferea warnings/notices de PHP: sin esto un warning se traducía en "los badges nunca aparecen", sin error visible.
ob_start();
function responderVerificar($data) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode($data);
	exit;
}
set_exception_handler(function ($e) { responderVerificar(['ok' => false, 'message' => $e->getMessage()]); });

$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);

if (!$filas || $trimestre < 1 || $trimestre > 4 || $anio < 2000) {
	responderVerificar(['ok' => true, 'estados' => []]);
}

$cacheSector = [];
$cachePosId  = [];

// Trae la fila completa, no solo gana_categoria: se compara cada campo contra lo que se va a guardar para no marcar "Se actualiza" cuando nada cambió.
$stmtExistente = $mysqli->prepare(
	'SELECT cuota_total, venta_total, cumplimiento_pct, gana_categoria, gana_total,
	        rebate_pct, pre_rebate, rebate_maximo_110, rebate_real_vol
	 FROM repositorio_cumplimiento_cuota
	 WHERE pos_id = ? AND sector = ? AND linea = ? AND trimestre = ? AND anio = ? AND eliminado_en IS NULL LIMIT 1'
);

// Mismo redondeo que cumplimiento_guardar.php al guardar, para no dar falsos "cambios" por formato (ej. "62" vs "62.00").
function filaSinCambios($existente, $fila, $ganaCategoriaNueva, $ganaTotalNueva) {
	$aNum = function ($v, $decimales) {
		return is_numeric($v) ? round((float) $v, $decimales) : null;
	};
	if ($existente['gana_categoria'] !== $ganaCategoriaNueva) return false;
	if ($existente['gana_total'] !== $ganaTotalNueva) return false;
	if ((float) $existente['cuota_total'] !== $aNum($fila['cuota_total'] ?? null, 2)) return false;
	if ((float) $existente['venta_total'] !== $aNum($fila['venta_total'] ?? null, 2)) return false;
	if (round((float) $existente['cumplimiento_pct'], 4) !== $aNum($fila['cumplimiento_pct'] ?? null, 4)) return false;
	if (round((float) $existente['rebate_real_vol'], 2) !== $aNum($fila['rebate_real_vol'] ?? null, 2)) return false;
	$rebatePctExistente = $existente['rebate_pct'] !== null ? round((float) $existente['rebate_pct'], 4) : null;
	if ($rebatePctExistente !== $aNum($fila['rebate_pct'] ?? null, 4)) return false;
	$preRebateExistente = $existente['pre_rebate'] !== null ? round((float) $existente['pre_rebate'], 2) : null;
	if ($preRebateExistente !== $aNum($fila['pre_rebate'] ?? null, 2)) return false;
	$rebateMax110Existente = $existente['rebate_maximo_110'] !== null ? round((float) $existente['rebate_maximo_110'], 2) : null;
	if ($rebateMax110Existente !== $aNum($fila['rebate_maximo_110'] ?? null, 2)) return false;
	return true;
}

$estados = [];
foreach ($filas as $indice => $fila) {
	$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
	$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
	$sectorCrudo  = repositorio_normalizar_texto($fila['sector'] ?? '');
	$linea        = is_numeric($fila['linea'] ?? null) ? (int) $fila['linea'] : 1;
	$ganaCategoriaNueva = strtolower((string) ($fila['gana_categoria'] ?? 'no_gana')) === 'gana' ? 'gana' : 'no_gana';
	$ganaTotalNueva     = strtolower((string) ($fila['gana_total'] ?? 'no_gana')) === 'gana' ? 'gana' : 'no_gana';

	if ($clienteExcel === '' || $sectorCrudo === '') {
		$estados[$indice] = ['estado' => 'sin_cliente'];
		continue;
	}

	if (!array_key_exists($sectorCrudo, $cacheSector)) {
		$cacheSector[$sectorCrudo] = resolverSectorReal($mysqli, $sectorCrudo);
	}
	$sector = $cacheSector[$sectorCrudo] ?? $sectorCrudo;

	$clavePos = $clienteExcel.'|'.$cediExcel;
	if (!array_key_exists($clavePos, $cachePosId)) {
		$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
	}
	$posId = $cachePosId[$clavePos];

	if (!$posId) {
		$estados[$indice] = ['estado' => 'sin_cliente'];
		continue;
	}
	if (!$stmtExistente) {
		$estados[$indice] = ['estado' => 'nuevo'];
		continue;
	}

	$stmtExistente->bind_param('ssiii', $posId, $sector, $linea, $trimestre, $anio);
	$stmtExistente->execute();
	$existente = $stmtExistente->get_result()->fetch_assoc();

	if (!$existente) {
		$estados[$indice] = ['estado' => 'nuevo'];
	} elseif (filaSinCambios($existente, $fila, $ganaCategoriaNueva, $ganaTotalNueva)) {
		$estados[$indice] = ['estado' => 'sin_cambios'];
	} elseif ($existente['gana_categoria'] === $ganaCategoriaNueva) {
		$estados[$indice] = ['estado' => 'actualiza'];
	} elseif ($ganaCategoriaNueva === 'gana') {
		$estados[$indice] = ['estado' => 'mejora'];
	} else {
		$estados[$indice] = ['estado' => 'empeora'];
	}
}
if ($stmtExistente) $stmtExistente->close();

responderVerificar(['ok' => true, 'estados' => $estados]);
?>
