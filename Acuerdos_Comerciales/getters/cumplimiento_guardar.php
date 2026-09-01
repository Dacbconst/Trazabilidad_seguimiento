<?php
// Paso 2 de la subida de Cumplimiento de Cuota: UPSERT fila por fila sobre
// (pos_id, sector, trimestre, anio), nunca aborta todo por un error puntual.
// Re-subir revive una fila borrada lógicamente (limpia eliminado_en/eliminado_por).
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

// Bufferea la salida para que un warning de PHP no rompa el JSON de respuesta.
ob_start();

function responder($ok, $message, $extra = []) {
	while (ob_get_level() > 0) { ob_end_clean(); }
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

// Cubre Exception y Error: sin esto, un fallo real imprimía HTML en vez de JSON.
set_exception_handler(function ($e) { responder(false, 'No se pudo guardar: '.$e->getMessage()); });

$body      = json_decode(file_get_contents('php://input'), true);
$filas     = is_array($body['filas'] ?? null) ? $body['filas'] : [];
$trimestre = (int) ($body['trimestre'] ?? 0);
$anio      = (int) ($body['anio'] ?? 0);

if (!$filas) {
	responder(false, 'No hay filas para guardar.');
}
if ($trimestre < 1 || $trimestre > 4) {
	responder(false, 'Trimestre inválido.');
}
$anioActual = (int) date('Y');
if ($anio < $anioActual - 1 || $anio > $anioActual + 1) {
	responder(false, 'Año inválido.');
}

$usuarioSesion = $_SESSION['user_id'] ?? null;
$guardadas = 0;
$nuevas = 0;
$actualizadas = 0;
$sinCambios = 0;
$errores = []; // [{indice, fila, motivo}, ...] — NO se guardaron.
$avisos  = []; // [{indice, fila, motivo}, ...] — SÍ se guardaron, pero conviene revisar.

$mysqli->begin_transaction();
try {
	// Orden del SET importa: MySQL evalúa izquierda a derecha, así "gana_categoria_anterior"
	// captura el valor previo antes de que "gana_categoria" se pise (ver datos/cumplimiento_cuota_schema.sql).
	$stmt = $mysqli->prepare(
		'INSERT INTO repositorio_cumplimiento_cuota
		 (pos_id, cliente_excel, cedi_excel, plan_excel, sector, linea, trimestre, anio,
		  cuota_total, venta_total, cumplimiento_pct, gana_categoria, gana_total,
		  rebate_pct, pre_rebate, rebate_maximo_110, rebate_real_vol, actualizado_por)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
		   gana_categoria_anterior = gana_categoria,
		   cliente_excel = VALUES(cliente_excel), cedi_excel = VALUES(cedi_excel), plan_excel = VALUES(plan_excel),
		   cuota_total = VALUES(cuota_total), venta_total = VALUES(venta_total),
		   cumplimiento_pct = VALUES(cumplimiento_pct),
		   gana_categoria = VALUES(gana_categoria), gana_total = VALUES(gana_total),
		   rebate_pct = VALUES(rebate_pct), pre_rebate = VALUES(pre_rebate),
		   rebate_maximo_110 = VALUES(rebate_maximo_110), rebate_real_vol = VALUES(rebate_real_vol),
		   actualizado_por = VALUES(actualizado_por), updated_at = NOW(),
		   eliminado_en = NULL, eliminado_por = NULL'
	);
	if (!$stmt) throw new Exception('El módulo de Cumplimiento de Cuota todavía no está disponible. Avisa al equipo técnico.');

	$cacheSector = []; // cache por subida, mismo criterio que cuotas_guardar.php
	$cachePosId  = [];

	foreach ($filas as $indice => $fila) {
		$clienteExcel = repositorio_normalizar_texto($fila['cliente_excel'] ?? '');
		$cediExcel    = repositorio_normalizar_texto($fila['cedi_excel'] ?? '');
		$plan         = repositorio_normalizar_texto($fila['plan_excel'] ?? '');
		$sectorCrudo  = repositorio_normalizar_texto($fila['sector'] ?? '');
		// "linea" distingue 2+ filas del mismo cliente+Sector (ver
		// repositorio_parsear_cumplimiento_cuota()); `?: 1` solo por compatibilidad vieja.
		$linea        = is_numeric($fila['linea'] ?? null) ? (int) $fila['linea'] : 1;
		$cuotaTotal   = is_numeric($fila['cuota_total'] ?? null) ? round((float) $fila['cuota_total'], 2) : 0.0;
		$ventaTotal   = is_numeric($fila['venta_total'] ?? null) ? round((float) $fila['venta_total'], 2) : 0.0;
		$cumplPct     = is_numeric($fila['cumplimiento_pct'] ?? null) ? round((float) $fila['cumplimiento_pct'], 4) : 0.0;
		$ganaCategoria = strtolower((string) ($fila['gana_categoria'] ?? 'no_gana')) === 'gana' ? 'gana' : 'no_gana';
		$ganaTotal     = strtolower((string) ($fila['gana_total'] ?? 'no_gana')) === 'gana' ? 'gana' : 'no_gana';
		$rebatePct     = is_numeric($fila['rebate_pct'] ?? null) ? round((float) $fila['rebate_pct'], 4) : null;
		$preRebate     = is_numeric($fila['pre_rebate'] ?? null) ? round((float) $fila['pre_rebate'], 2) : null;
		$rebateMax110  = is_numeric($fila['rebate_maximo_110'] ?? null) ? round((float) $fila['rebate_maximo_110'], 2) : null;
		$rebateRealVol = is_numeric($fila['rebate_real_vol'] ?? null) ? round((float) $fila['rebate_real_vol'], 2) : 0.0;
		$etiqueta      = $clienteExcel !== '' ? $clienteExcel.' / '.$sectorCrudo : '(fila vacía)';

		if ($clienteExcel === '' || $sectorCrudo === '') {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'Falta Cliente o Categoría'];
			continue;
		}

		if (!array_key_exists($sectorCrudo, $cacheSector)) {
			$cacheSector[$sectorCrudo] = resolverSectorReal($mysqli, $sectorCrudo);
		}
		$sectorResuelto = $cacheSector[$sectorCrudo];
		$sector = $sectorCrudo;
		if ($sectorResuelto === null) {
			$avisos[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar la categoría "'.$sectorCrudo.'" en el catálogo. Revisar con JW.'];
		} elseif ($sectorResuelto !== $sectorCrudo) {
			$sector = $sectorResuelto;
			$etiqueta = $clienteExcel.' / '.$sector;
		}

		$clavePos = $clienteExcel.'|'.$cediExcel;
		if (!array_key_exists($clavePos, $cachePosId)) {
			$cachePosId[$clavePos] = resolverPosIdCliente($mysqli, $clienteExcel, $cediExcel);
		}
		$posId = $cachePosId[$clavePos];
		if (!$posId) {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo identificar el cliente'];
			continue;
		}

		// `linea` ya diferencia cada renglón: 2 filas del mismo cliente+Sector son legítimas, no duplicado.
		$stmt->bind_param(
			'sssssiiidddssddddi',
			$posId, $clienteExcel, $cediExcel, $plan, $sector, $linea, $trimestre, $anio,
			$cuotaTotal, $ventaTotal, $cumplPct, $ganaCategoria, $ganaTotal,
			$rebatePct, $preRebate, $rebateMax110, $rebateRealVol, $usuarioSesion
		);
		if ($stmt->execute()) {
			$guardadas++;
			if ($stmt->affected_rows === 1) { $nuevas++; }
			elseif ($stmt->affected_rows === 2) { $actualizadas++; }
			else { $sinCambios++; }
		} else {
			$errores[] = ['indice' => $indice, 'fila' => $etiqueta, 'motivo' => 'No se pudo guardar esta fila'];
		}
	}
	$stmt->close();

	$mysqli->commit();
} catch (Throwable $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar: '.$e->getMessage());
}

$omitidas = count($errores);
$partesDetalle = [];
if ($nuevas > 0) $partesDetalle[] = "$nuevas fila(s) nueva(s)";
if ($actualizadas > 0) $partesDetalle[] = "$actualizadas actualizada(s)";
if ($sinCambios > 0) $partesDetalle[] = "$sinCambios sin cambios (ya existían igual)";
$partesMensaje = [$partesDetalle ? 'Se guardaron '.implode(', ', $partesDetalle).'.' : 'No se guardó ninguna fila nueva.'];
if ($omitidas > 0) $partesMensaje[] = "$omitidas fila(s) no se guardaron. Revisa el detalle.";
if ($avisos) $partesMensaje[] = count($avisos).' fila(s) necesitan revisión. Revisa el detalle.';
responder(true, implode(' ', $partesMensaje), [
	'guardadas' => $guardadas, 'nuevas' => $nuevas, 'actualizadas' => $actualizadas, 'sin_cambios' => $sinCambios,
	'omitidas' => $omitidas, 'errores' => $errores, 'avisos' => $avisos,
]);
?>
