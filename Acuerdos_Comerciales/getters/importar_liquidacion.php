<?php
// Sube y procesa el Excel trimestral de Liquidación (Directa o Distribuidor):
// parsea las 2 hojas relevantes (ver includes/liquidacion_import.php), intenta
// matchear cada fila contra un acuerdo_id, y guarda todo en las 3 tablas de
// datos/liquidacion_schema.sql. Solo superdesarrollador (mismo criterio que
// el resto de Liquidación, ver CLAUDE.md).
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

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$canal = $_POST['canal'] ?? '';
$anio  = (int) ($_POST['anio'] ?? 0);

if (!in_array($canal, ['directa', 'distribuidor'], true)) {
	responder(false, 'Canal inválido.');
}
if ($anio < 2020 || $anio > 2100) {
	responder(false, 'Año inválido.');
}
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
	responder(false, 'No se recibió el archivo Excel (o falló la subida).');
}
if (!xlsx_disponible()) {
	responder(false, 'No se pudo leer el archivo. Avisa al equipo técnico.');
}

$rutaTmp = $_FILES['archivo']['tmp_name'];
$nombreArchivo = basename($_FILES['archivo']['name']);

$resultCuota = liquidacion_parsear_cuota_categoria($rutaTmp, $canal);
if (isset($resultCuota['error'])) {
	responder(false, 'Error leyendo la hoja de cuota/venta: '.$resultCuota['error']);
}
$resultVisibilidad = liquidacion_parsear_visibilidad($rutaTmp, $canal);
if (isset($resultVisibilidad['error'])) {
	responder(false, 'Error leyendo la hoja de visibilidad: '.$resultVisibilidad['error']);
}

$filasCuota = $resultCuota['filas'];
$filasVisibilidad = $resultVisibilidad['filas'];

if (!$filasCuota && !$filasVisibilidad) {
	responder(false, 'El archivo no tiene filas de datos en ninguna de las dos hojas esperadas.');
}

// El período NO lo elige quien sube el archivo — se lee directo de las
// columnas de mes de la hoja de cuota/venta (ver
// liquidacion_parsear_cuota_categoria()), porque no hay ninguna frecuencia
// fija confirmada (puede ser mensual, trimestral, o lo que sea que JW mande
// esa vez). Sin filas de cuota no hay forma de saber el período, así que la
// hoja de visibilidad sola no alcanza para procesar el archivo.
if (!$filasCuota) {
	responder(false, 'La hoja de cuota/venta no tiene filas — sin eso no se puede determinar el período de esta importación.');
}
$mesInicio = $resultCuota['mes_inicio'];
$mesFin    = $resultCuota['mes_fin'];

$mysqli->begin_transaction();
try {
	$usuarioSesion = $_SESSION['user_id'] ?? null;

	$stmt = $mysqli->prepare(
		"INSERT INTO repositorio_liquidacion_importaciones (canal, anio, mes_inicio, mes_fin, nombre_archivo, subido_por, estado, total_filas, filas_pendientes)
		 VALUES (?, ?, ?, ?, ?, ?, 'procesando', 0, 0)"
	);
	if (!$stmt) throw new Exception('No se pudo preparar el registro de importación.');
	$stmt->bind_param('siiisi', $canal, $anio, $mesInicio, $mesFin, $nombreArchivo, $usuarioSesion);
	$stmt->execute();
	$importacionId = $stmt->insert_id;
	$stmt->close();

	// Cache en memoria del match por (cedi/distribuidor + cliente/nombre): las
	// hojas de cuota y visibilidad suelen repetir el mismo cliente varias
	// veces (una fila por categoría) — matchear una sola vez por cliente en
	// vez de una por fila evita repetir la misma consulta a la base decenas
	// de veces para el mismo cliente.
	$cacheMatch = [];
	$matchearConCache = function ($cedi, $cliente) use ($mysqli, $canal, $mesInicio, $mesFin, $anio, &$cacheMatch) {
		$clave = $cedi.'|'.$cliente;
		if (!isset($cacheMatch[$clave])) {
			$cacheMatch[$clave] = liquidacion_matchear_fila($mysqli, $canal, $cedi, $cliente, $mesInicio, $mesFin, $anio);
		}
		return $cacheMatch[$clave];
	};

	$totalFilas = 0;
	$filasPendientes = 0;

	$stmtCuota = $mysqli->prepare(
		"INSERT INTO repositorio_liquidacion_cuota_categoria
		 (importacion_id, cedi_o_distribuidor, cliente_o_nombre, codigo, ruc, categoria,
		  cuota_total_excel, venta_total_excel, rebate_pct_excel, rebate_dolares_excel,
		  rebate_maximo_110, cumplimiento, acuerdo_id, estado_match)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
	);
	if (!$stmtCuota) throw new Exception('No se pudo preparar el guardado de cuota/categoría.');

	foreach ($filasCuota as $fila) {
		$match = $matchearConCache($fila['cedi_o_distribuidor'], $fila['cliente_o_nombre']);
		$acuerdoId = $match['acuerdo_id'];
		$estadoMatch = $match['estado_match'];
		if ($estadoMatch !== 'matcheado') $filasPendientes++;
		$totalFilas++;

		// El orden de los parámetros tiene que calzar EXACTO con el orden de
		// columnas del INSERT de arriba (..., cumplimiento, acuerdo_id, estado_match)
		// — acuerdo_id va como 'i' aunque pueda ser NULL, mysqli lo manda bien igual.
		$stmtCuota->bind_param(
			'isssssdddddsis',
			$importacionId, $fila['cedi_o_distribuidor'], $fila['cliente_o_nombre'], $fila['codigo'], $fila['ruc'], $fila['categoria'],
			$fila['cuota_total_excel'], $fila['venta_total_excel'], $fila['rebate_pct_excel'], $fila['rebate_dolares_excel'],
			$fila['rebate_maximo_110'], $fila['cumplimiento'], $acuerdoId, $estadoMatch
		);
		$stmtCuota->execute();
	}
	$stmtCuota->close();

	$stmtVis = $mysqli->prepare(
		"INSERT INTO repositorio_liquidacion_visibilidad
		 (importacion_id, cedi_o_distribuidor, cliente_o_nombre, cantidad_cabecera, cantidad_isla, cantidad_percha,
		  pago_cabecera, pago_isla, pago_percha, pago_total_excel, cumplimiento, acuerdo_id, estado_match)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
	);
	if (!$stmtVis) throw new Exception('No se pudo preparar el guardado de visibilidad.');

	foreach ($filasVisibilidad as $fila) {
		$match = $matchearConCache($fila['cedi_o_distribuidor'], $fila['cliente_o_nombre']);
		$acuerdoId = $match['acuerdo_id'];
		$estadoMatch = $match['estado_match'];
		if ($estadoMatch !== 'matcheado') $filasPendientes++;
		$totalFilas++;

		$stmtVis->bind_param(
			'issiiiddddsis',
			$importacionId, $fila['cedi_o_distribuidor'], $fila['cliente_o_nombre'],
			$fila['cantidad_cabecera'], $fila['cantidad_isla'], $fila['cantidad_percha'],
			$fila['pago_cabecera'], $fila['pago_isla'], $fila['pago_percha'], $fila['pago_total_excel'],
			$fila['cumplimiento'], $acuerdoId, $estadoMatch
		);
		$stmtVis->execute();
	}
	$stmtVis->close();

	$stmt = $mysqli->prepare(
		"UPDATE repositorio_liquidacion_importaciones SET estado = 'completado', total_filas = ?, filas_pendientes = ? WHERE id = ?"
	);
	$stmt->bind_param('iii', $totalFilas, $filasPendientes, $importacionId);
	$stmt->execute();
	$stmt->close();

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo procesar el Excel: '.$e->getMessage());
}

responder(true, 'Excel procesado correctamente.', [
	'importacion_id'   => $importacionId,
	'total_filas'      => $totalFilas,
	'filas_pendientes' => $filasPendientes,
	'filas_ok'         => $totalFilas - $filasPendientes,
]);
?>
