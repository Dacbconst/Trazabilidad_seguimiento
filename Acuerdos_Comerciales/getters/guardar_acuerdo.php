<?php
// Guarda (crea o actualiza) un Acuerdo PDV completo: cabecera en
// repositorio_acuerdos + sus 4 tablas en repositorio_acuerdo_lineas.
// Editar = borrar todas las líneas del acuerdo e insertar de nuevo el set
// actual — el formulario siempre manda el estado completo de las 4 tablas,
// no hay edición incremental de una sola fila desde el backend.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../db_connect.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['admin', 'desarrollador', 'superdesarrollador'])) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

function responder($ok, $message, $extra = []) {
	echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
	exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
	responder(false, 'Cuerpo de la petición inválido.');
}

$acuerdoId  = isset($body['acuerdo_id']) ? (int) $body['acuerdo_id'] : 0;
$posId      = trim($body['pos_id'] ?? '');
$anio       = (int) ($body['anio'] ?? 0);
$mesInicio  = (int) ($body['mes_inicio'] ?? -1);
$mesFin     = (int) ($body['mes_fin'] ?? -1);
$estado     = $body['estado'] ?? 'borrador';
$lineas     = is_array($body['lineas'] ?? null) ? $body['lineas'] : [];

$estadosPermitidosDesdeForm = ['borrador', 'generado', 'enviado'];

// ---------- Validaciones de cabecera ----------
if ($posId === '') {
	responder(false, 'Selecciona un Distribuidor.');
}
if ($anio < 2020 || $anio > 2100) {
	responder(false, 'Año inválido.');
}
if ($mesInicio < 0 || $mesInicio > 11 || $mesFin < 0 || $mesFin > 11) {
	responder(false, 'Periodo del acuerdo inválido.');
}
if ($mesFin < $mesInicio) {
	responder(false, 'El periodo del acuerdo debe ser de meses consecutivos.');
}
if (!in_array($estado, $estadosPermitidosDesdeForm, true)) {
	responder(false, 'Estado inválido.');
}

// pos_id debe existir en el maestro real — no hay FK, se valida en código.
$stmt = $mysqli->prepare('SELECT pos_id FROM repositorio_locales_dtt2 WHERE pos_id = ? LIMIT 1');
$stmt->bind_param('s', $posId);
$stmt->execute();
$existePos = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$existePos) {
	responder(false, 'El Distribuidor seleccionado no existe en el maestro de locales.');
}

$cantidadMeses = $mesFin - $mesInicio + 1;

// ---------- Validación y normalización de las 4 tablas ----------
function normalizarValores(array $valores, $cantidadMeses, $mesInicio) {
	$out = [];
	for ($i = 0; $i < $cantidadMeses; $i++) {
		$mes = $mesInicio + $i;
		$out[(string) $mes] = round((float) ($valores[$i] ?? 0), 2);
	}
	return $out;
}

$filasNormalizadas = ['meta_compra' => [], 'cabecera' => [], 'ruma' => [], 'percha' => []];

foreach (['meta_compra', 'cabecera'] as $tipo) {
	foreach (($lineas[$tipo] ?? []) as $orden => $fila) {
		$segmento = trim($fila['segmento'] ?? '');
		$categoria = trim($fila['categoria'] ?? '');
		$marca = trim($fila['marca'] ?? '');
		if ($segmento === '' || $categoria === '' || $marca === '') continue; // fila incompleta, se ignora
		$valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
		$rebate = $tipo === 'meta_compra' ? max(0, (float) ($fila['rebate_pct'] ?? 0)) : null;
		$filasNormalizadas[$tipo][] = [
			'segmento' => $segmento,
			'categoria' => $categoria,
			'marca' => $marca,
			'rebate_pct' => $rebate,
			'valores_mensuales' => normalizarValores($valores, $cantidadMeses, $mesInicio),
			'orden' => $orden,
		];
	}
}

foreach (($lineas['ruma'] ?? []) as $orden => $fila) {
	$segmento = trim($fila['segmento'] ?? '');
	$categoria = trim($fila['categoria'] ?? '');
	$marca = trim($fila['marca'] ?? '');
	if ($segmento === '' || $categoria === '' || $marca === '') continue;
	$filasNormalizadas['ruma'][] = [
		'segmento' => $segmento,
		'categoria' => $categoria,
		'marca' => $marca,
		'valor_mensual_unico' => round((float) ($fila['valor_mensual_unico'] ?? 0), 2),
		'orden' => $orden,
	];
}

foreach (($lineas['percha'] ?? []) as $orden => $fila) {
	$marca = trim($fila['marca'] ?? '');
	if ($marca === '') continue;
	$cantidadMaxPercha = (int) ($fila['cantidad_max_percha'] ?? 0);
	if ($cantidadMaxPercha < 0 || $cantidadMaxPercha > 5) {
		responder(false, 'La cantidad máxima de perchas por marca no puede superar 5.');
	}
	$valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
	$filasNormalizadas['percha'][] = [
		'marca' => $marca,
		'participacion' => trim($fila['participacion'] ?? ''),
		'cantidad_max_percha' => $cantidadMaxPercha,
		'precio_percha' => round((float) ($fila['precio_percha'] ?? 40), 2),
		'valores_mensuales' => normalizarValores($valores, $cantidadMeses, $mesInicio),
		'orden' => $orden,
	];
}

// ---------- Transacción ----------
$mysqli->begin_transaction();

try {
	if ($acuerdoId > 0) {
		$stmt = $mysqli->prepare('SELECT id, documento_no, estado, fecha_generacion FROM repositorio_acuerdos WHERE id = ? LIMIT 1');
		$stmt->bind_param('i', $acuerdoId);
		$stmt->execute();
		$actual = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$actual) {
			throw new Exception('El acuerdo a actualizar ya no existe.');
		}

		$fechaGeneracion = $actual['fecha_generacion'];
		if ($estado !== 'borrador' && $fechaGeneracion === null) {
			$fechaGeneracion = date('Y-m-d');
		}

		$stmt = $mysqli->prepare(
			'UPDATE repositorio_acuerdos
			 SET pos_id = ?, anio = ?, mes_inicio = ?, mes_fin = ?, estado = ?, fecha_generacion = ?
			 WHERE id = ?'
		);
		$stmt->bind_param('siiissi', $posId, $anio, $mesInicio, $mesFin, $estado, $fechaGeneracion, $acuerdoId);
		$stmt->execute();
		$stmt->close();

		$documentoNo = $actual['documento_no'];
	} else {
		$fechaGeneracion = $estado !== 'borrador' ? date('Y-m-d') : null;

		// documento_no autogenerado ADN-{anio}-{secuencia}; reintenta si choca con el UNIQUE.
		$stmtSeq = $mysqli->prepare('SELECT COUNT(*) AS total FROM repositorio_acuerdos WHERE anio = ?');
		$stmtSeq->bind_param('i', $anio);
		$stmtSeq->execute();
		$seq = (int) $stmtSeq->get_result()->fetch_assoc()['total'] + 1;
		$stmtSeq->close();

		$intentos = 0;
		$acuerdoId = 0;
		do {
			$documentoNo = sprintf('ADN-%d-%04d', $anio, $seq);
			$stmt = $mysqli->prepare(
				'INSERT INTO repositorio_acuerdos (documento_no, pos_id, anio, mes_inicio, mes_fin, estado, fecha_generacion)
				 VALUES (?, ?, ?, ?, ?, ?, ?)'
			);
			$stmt->bind_param('ssiiiss', $documentoNo, $posId, $anio, $mesInicio, $mesFin, $estado, $fechaGeneracion);
			$insertOk = $stmt->execute();
			if ($insertOk) {
				$acuerdoId = $stmt->insert_id;
			}
			$duplicado = !$insertOk && $stmt->errno === 1062;
			$stmt->close();
			$seq++;
			$intentos++;
		} while ($duplicado && $intentos < 5);

		if (!$acuerdoId) {
			throw new Exception('No se pudo generar el número de documento.');
		}
	}

	$stmt = $mysqli->prepare('DELETE FROM repositorio_acuerdo_lineas WHERE acuerdo_id = ?');
	$stmt->bind_param('i', $acuerdoId);
	$stmt->execute();
	$stmt->close();

	$stmtLinea = $mysqli->prepare(
		'INSERT INTO repositorio_acuerdo_lineas
		 (acuerdo_id, tipo, segmento, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha, valores_mensuales, valor_mensual_unico, orden)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);

	// Tipos: acuerdo_id(i) tipo(s) segmento(s) categoria(s) marca(s) rebate_pct(d)
	// cantidad_max_percha(i) participacion_pct(s) precio_percha(d) valores_mensuales(s) valor_mensual_unico(d) orden(i)
	$tiposBind = 'issssdisdsdi';

	foreach (['meta_compra', 'cabecera'] as $tipo) {
		foreach ($filasNormalizadas[$tipo] as $fila) {
			// JSON_FORCE_OBJECT: sin esto, un periodo que arranca en Enero (mes 0)
			// produce claves "0","1","2" consecutivas y json_encode las convierte
			// en un ARRAY JSON en vez del objeto {"0":...} que espera el esquema.
			$valoresJson = json_encode($fila['valores_mensuales'], JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
			$rebate = $fila['rebate_pct'];
			$cantidadMaxPercha = null;
			$participacionPct = null;
			$precioPercha = null;
			$valorMensualUnico = null;
			$stmtLinea->bind_param(
				$tiposBind,
				$acuerdoId, $tipo, $fila['segmento'], $fila['categoria'], $fila['marca'],
				$rebate, $cantidadMaxPercha, $participacionPct, $precioPercha, $valoresJson, $valorMensualUnico, $fila['orden']
			);
			$stmtLinea->execute();
		}
	}

	$tipo = 'ruma';
	foreach ($filasNormalizadas['ruma'] as $fila) {
		$rebate = null;
		$cantidadMaxPercha = null;
		$participacionPct = null;
		$precioPercha = null;
		$valoresJson = null;
		$stmtLinea->bind_param(
			$tiposBind,
			$acuerdoId, $tipo, $fila['segmento'], $fila['categoria'], $fila['marca'],
			$rebate, $cantidadMaxPercha, $participacionPct, $precioPercha, $valoresJson, $fila['valor_mensual_unico'], $fila['orden']
		);
		$stmtLinea->execute();
	}

	$tipo = 'percha';
	foreach ($filasNormalizadas['percha'] as $fila) {
		$segmento = null;
		$categoria = null;
		$rebate = null;
		$valorMensualUnico = null;
		$participacionPct = $fila['participacion'] !== '' ? $fila['participacion'] : null;
		$valoresJson = json_encode($fila['valores_mensuales'], JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
		$stmtLinea->bind_param(
			$tiposBind,
			$acuerdoId, $tipo, $segmento, $categoria, $fila['marca'],
			$rebate, $fila['cantidad_max_percha'], $participacionPct, $precioPercha, $valoresJson, $valorMensualUnico, $fila['orden']
		);
		$stmtLinea->execute();
	}

	$stmtLinea->close();

	$mysqli->commit();
} catch (Exception $e) {
	$mysqli->rollback();
	responder(false, 'No se pudo guardar el acuerdo: '.$e->getMessage());
}

responder(true, 'Acuerdo guardado correctamente.', [
	'acuerdo_id'   => $acuerdoId,
	'documento_no' => $documentoNo,
	'estado'       => $estado,
]);
?>
