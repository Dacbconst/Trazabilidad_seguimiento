<?php
// Chequeo EN VIVO (solo lectura, nunca escribe) antes de confirmar el
// guardado — mismo espíritu que getters/cuotas_verificar_estado.php: para
// cada fila ya parseada, resuelve a qué pos_id/sector real corresponde y
// dice qué va a pasar al guardar (Nuevo / Se actualiza / Sin cambios /
// Cambió de estado), ANTES de que el usuario confirme. Se llama de nuevo
// cada vez que cambia el Año elegido (el trimestre ya se sabe del archivo,
// pero el año recién se elige en este paso).
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

// Ver nota completa en cumplimiento_guardar.php: bufferea cualquier
// warning/notice de PHP para que nunca se mezcle con el JSON de respuesta.
// Este chequeo falla en silencio del lado del cliente (no bloquea la
// previsualización), así que sin esto un warning acá se traducía en "los
// badges de Al Guardar nunca aparecen", sin ningún error visible.
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

$stmtExistente = $mysqli->prepare(
	'SELECT gana_categoria FROM repositorio_cumplimiento_cuota
	 WHERE pos_id = ? AND sector = ? AND trimestre = ? AND anio = ? AND eliminado_en IS NULL LIMIT 1'
);

$estados = [];
foreach ($filas as $indice => $fila) {
	$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
	$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
	$sectorCrudo  = repositorio_normalizar_texto($fila['sector'] ?? '');
	$ganaCategoriaNueva = strtolower((string) ($fila['gana_categoria'] ?? 'no_gana')) === 'gana' ? 'gana' : 'no_gana';

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

	$stmtExistente->bind_param('ssii', $posId, $sector, $trimestre, $anio);
	$stmtExistente->execute();
	$existente = $stmtExistente->get_result()->fetch_assoc();

	if (!$existente) {
		$estados[$indice] = ['estado' => 'nuevo'];
	} elseif ($existente['gana_categoria'] === $ganaCategoriaNueva) {
		// Solo se comparó GANA POR CATEGORÍA, no la fila completa — puede haber
		// cambiado Venta/Cumplimiento/Rebate igual (venta real que avanza a
		// mitad del trimestre sin cruzar el 80%). "Actualiza", no "Sin
		// cambios" — sería afirmar algo que este chequeo no verificó.
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
