<?php
// Guarda (crea o actualiza) un Acuerdo PDV completo: cabecera en
// repositorio_acuerdos + sus 4 tablas en repositorio_acuerdo_lineas.
// Editar = borrar todas las líneas del acuerdo e insertar de nuevo el set
// actual — el formulario siempre manda el estado completo de las 4 tablas,
// no hay edición incremental de una sola fila desde el backend.
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/acta_pdf.php';
require_once __DIR__.'/../db_connect.php';
require_once __DIR__.'/../vendor/autoload.php';
iniciar_sesion();
header('Content-Type: application/json; charset=utf-8');

if (!login_check() || !rolPermitido(['desarrollador', 'superdesarrollador'])) {
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
$sinVisibilidad = !empty($body['sin_visibilidad']);
$lineas     = is_array($body['lineas'] ?? null) ? $body['lineas'] : [];
// Fase 2 del Repositorio de Cuotas (2026-08-25): si este Acuerdo se generó
// desde una Acta precargada, registrar.js manda de dónde salió — se valida
// que el pos_id coincida con el que se está guardando (nunca se confía en
// el origen tal cual llega) antes de marcar esas filas como consumidas, ver
// más abajo, después del commit.
$origenPrecarga = is_array($body['origen_precarga'] ?? null) ? $body['origen_precarga'] : null;

$estadosPermitidosDesdeForm = ['borrador', 'generado', 'enviado'];

// ---------- Validaciones de cabecera ----------
if ($posId === '') {
	responder(false, 'Selecciona un Local.');
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
// Además debe pertenecer al `supervisor` de la sesión: nadie puede guardar un
// Acuerdo para un cliente que no es suyo, aunque conozca su pos_id.
$supervisorSesion = $_SESSION['supervisor'] ?? null;
$stmt = $mysqli->prepare(
	'SELECT pos_id FROM repositorio_locales_supervisores_cliente WHERE pos_id = ? AND supervisor = ? LIMIT 1'
);
if (!$stmt) {
	responder(false, 'No se pudo validar el Local (maestro de locales no disponible). Avisar al equipo técnico.');
}
$stmt->bind_param('ss', $posId, $supervisorSesion);
$stmt->execute();
$existePos = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$existePos) {
	responder(false, 'El Local seleccionado no existe en el maestro de locales o no pertenece a tu cartera de clientes.');
}

// Regla de negocio (2026-08-23): solo puede haber UN Acta activa (no
// borrador, no anulada) por Local+Período — evita que dos analistas
// generen la misma Acta al mismo tiempo. "El primero que llega, gana": el
// segundo que intente guardar/generar (cualquier $estado distinto de
// 'borrador') para el mismo Local+Período se bloquea acá con un mensaje
// específico (`duplicado: true`) para que el frontend lo muestre con
// SweetAlert2, no el toast genérico de error. Los borradores quedan
// exentos a propósito — se puede seguir armando uno en paralelo, recién
// se bloquea al intentar generarlo de verdad.
if ($estado !== 'borrador') {
	$stmtDup = $mysqli->prepare(
		"SELECT d.pos_name FROM repositorio_acuerdos a
		 JOIN repositorio_locales_supervisores_cliente d ON d.pos_id = a.pos_id
		 WHERE a.pos_id = ? AND a.anio = ? AND a.mes_inicio = ? AND a.mes_fin = ?
		   AND a.estado NOT IN ('borrador', 'anulado')
		   AND a.id <> ?
		 LIMIT 1"
	);
	if ($stmtDup) {
		$stmtDup->bind_param('siiii', $posId, $anio, $mesInicio, $mesFin, $acuerdoId);
		$stmtDup->execute();
		$filaDup = $stmtDup->get_result()->fetch_assoc();
		$stmtDup->close();
		if ($filaDup) {
			responder(false, $filaDup['pos_name'].' ya tiene un Acta generada para este trimestre.', ['duplicado' => true]);
		}
	}
}

$cantidadMeses = $mesFin - $mesInicio + 1;

// ---------- Validación y normalización de las 4 tablas ----------
// max(0, ...): el cliente ya clampa montos negativos al tipear, esto es
// defensa adicional del lado del servidor (el guardado es por fetch(), no un
// submit nativo, así que nada obliga a pasar por esa validación de JS).
function normalizarValores(array $valores, $cantidadMeses, $mesInicio) {
	$out = [];
	for ($i = 0; $i < $cantidadMeses; $i++) {
		$mes = $mesInicio + $i;
		$out[(string) $mes] = round(max(0, (float) ($valores[$i] ?? 0)), 2);
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
		// Sector: solo Meta de Compras (2026-08-18, ver CLAUDE.md) — es el
		// nivel al que Trade MKT aprueba y rastrea el rebate de verdad
		// (BARRA/CREMA/LIQUIDO/POLVO), confirmado comparando contra el Excel
		// real de JW. Cabecera/Ruma/Percha no lo tienen, igual que Segmento.
		$sector = $tipo === 'meta_compra' ? (trim($fila['sector'] ?? '') ?: null) : null;
		$filasNormalizadas[$tipo][] = [
			'segmento' => $segmento,
			'sector' => $sector,
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
		'valor_mensual_unico' => round(max(0, (float) ($fila['valor_mensual_unico'] ?? 0)), 2),
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
	// Participación es texto libre en la UI (ej. "50%") pero igual debe ser un
	// número real y no negativo — mismo criterio que ya se valida en el
	// cliente (registrar.js), repetido acá porque el guardado es por fetch(),
	// no un submit nativo que fuerce pasar por esa validación.
	$participacion = trim($fila['participacion'] ?? '');
	$participacionNum = str_replace('%', '', $participacion);
	if ($participacion === '' || !is_numeric($participacionNum) || (float) $participacionNum < 0) {
		responder(false, 'La Participación de Perchas debe ser un número y no puede quedar vacía ni ser negativa.');
	}
	$valores = is_array($fila['valores'] ?? null) ? $fila['valores'] : [];
	$filasNormalizadas['percha'][] = [
		'marca' => $marca,
		'participacion' => $participacion,
		'cantidad_max_percha' => $cantidadMaxPercha,
		'precio_percha' => round((float) ($fila['precio_percha'] ?? 40), 2),
		'valores_mensuales' => normalizarValores($valores, $cantidadMeses, $mesInicio),
		'orden' => $orden,
	];
}

// Switch "Visibilidad y Espacios" (2026-08-24): si el usuario lo desactivó en
// el formulario, la zona queda bloqueada en pantalla y no debería mandar
// nada — esto es defensa adicional del lado del servidor (mismo motivo que
// normalizarValores() de arriba), por si llega algo igual por un estado
// stale del cliente.
if ($sinVisibilidad) {
	$filasNormalizadas['cabecera'] = [];
	$filasNormalizadas['ruma'] = [];
	$filasNormalizadas['percha'] = [];
}

// Un acuerdo completamente vacío solo se permite como borrador (work-in-
// progress) — mismo criterio que ya valida registrar.js del lado del
// cliente, repetido acá por si acaso.
if ($estado !== 'borrador'
	&& !$filasNormalizadas['meta_compra'] && !$filasNormalizadas['cabecera']
	&& !$filasNormalizadas['ruma'] && !$filasNormalizadas['percha']) {
	responder(false, 'Agrega al menos un producto en alguna tabla antes de generar el Acta (o guárdalo como borrador si todavía no está listo).');
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

		// updated_at = NOW() explícito: el ON UPDATE CURRENT_TIMESTAMP de la
		// columna solo se dispara si ALGUNA otra columna de esta fila cambia de
		// valor — pero editar un borrador normalmente solo toca las 4 tablas
		// (repositorio_acuerdo_lineas, aparte), así que la cabecera se vuelve a
		// guardar con los mismos valores de siempre y MySQL nunca actualizaba
		// la fecha. "Actualizado" en Mis Borradores debe reflejar el último
		// guardado real, edite lo que edite.
		$stmt = $mysqli->prepare(
			'UPDATE repositorio_acuerdos
			 SET pos_id = ?, anio = ?, mes_inicio = ?, mes_fin = ?, estado = ?, fecha_generacion = ?, sin_visibilidad = ?, updated_at = NOW()
			 WHERE id = ?'
		);
		$sinVisibilidadInt = (int) $sinVisibilidad;
		$stmt->bind_param('siiissii', $posId, $anio, $mesInicio, $mesFin, $estado, $fechaGeneracion, $sinVisibilidadInt, $acuerdoId);
		$stmt->execute();
		$stmt->close();

		$documentoNo = $actual['documento_no'];
	} else {
		$fechaGeneracion = $estado !== 'borrador' ? date('Y-m-d') : null;
		// creado_por se guarda UNA sola vez, al crear — nunca se pisa en el
		// UPDATE de arriba, así conserva quién lo hizo originalmente aunque
		// después lo edite/regenere otro usuario con permisos.
		$creadoPor = $_SESSION['user_id'] ?? null;

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
				'INSERT INTO repositorio_acuerdos (documento_no, pos_id, anio, mes_inicio, mes_fin, estado, fecha_generacion, creado_por, sin_visibilidad)
				 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
			);
			$sinVisibilidadInt = (int) $sinVisibilidad;
			$stmt->bind_param('ssiiissii', $documentoNo, $posId, $anio, $mesInicio, $mesFin, $estado, $fechaGeneracion, $creadoPor, $sinVisibilidadInt);
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
		 (acuerdo_id, tipo, segmento, sector, categoria, marca, rebate_pct, cantidad_max_percha, participacion_pct, precio_percha, valores_mensuales, valor_mensual_unico, orden)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);

	// Tipos: acuerdo_id(i) tipo(s) segmento(s) sector(s) categoria(s) marca(s) rebate_pct(d)
	// cantidad_max_percha(i) participacion_pct(s) precio_percha(d) valores_mensuales(s) valor_mensual_unico(d) orden(i)
	$tiposBind = 'isssssdisdsdi';

	foreach (['meta_compra', 'cabecera'] as $tipo) {
		foreach ($filasNormalizadas[$tipo] as $fila) {
			// JSON_FORCE_OBJECT: sin esto, un periodo que arranca en Enero (mes 0)
			// produce claves "0","1","2" consecutivas y json_encode las convierte
			// en un ARRAY JSON en vez del objeto {"0":...} que espera el esquema.
			$valoresJson = json_encode($fila['valores_mensuales'], JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
			$rebate = $fila['rebate_pct'];
			$sector = $fila['sector'];
			$cantidadMaxPercha = null;
			$participacionPct = null;
			$precioPercha = null;
			$valorMensualUnico = null;
			$stmtLinea->bind_param(
				$tiposBind,
				$acuerdoId, $tipo, $fila['segmento'], $sector, $fila['categoria'], $fila['marca'],
				$rebate, $cantidadMaxPercha, $participacionPct, $precioPercha, $valoresJson, $valorMensualUnico, $fila['orden']
			);
			$stmtLinea->execute();
		}
	}

	$tipo = 'ruma';
	foreach ($filasNormalizadas['ruma'] as $fila) {
		$rebate = null;
		$sector = null;
		$cantidadMaxPercha = null;
		$participacionPct = null;
		$precioPercha = null;
		$valoresJson = null;
		$stmtLinea->bind_param(
			$tiposBind,
			$acuerdoId, $tipo, $fila['segmento'], $sector, $fila['categoria'], $fila['marca'],
			$rebate, $cantidadMaxPercha, $participacionPct, $precioPercha, $valoresJson, $fila['valor_mensual_unico'], $fila['orden']
		);
		$stmtLinea->execute();
	}

	$tipo = 'percha';
	foreach ($filasNormalizadas['percha'] as $fila) {
		$segmento = null;
		$sector = null;
		$categoria = null;
		$rebate = null;
		$valorMensualUnico = null;
		$participacionPct = $fila['participacion'] !== '' ? $fila['participacion'] : null;
		$valoresJson = json_encode($fila['valores_mensuales'], JSON_NUMERIC_CHECK | JSON_FORCE_OBJECT);
		$stmtLinea->bind_param(
			$tiposBind,
			$acuerdoId, $tipo, $segmento, $sector, $categoria, $fila['marca'],
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

// Consumir la Acta precargada de origen (Fase 2, 2026-08-25): las filas de
// repositorio_cuota_cliente que la generaron pasan a 'usada' + quedan
// enlazadas al Acuerdo real — desaparecen de la campanita de alertas y
// quedan protegidas de "Eliminar" en el Repositorio de Cuotas (ver
// getters/repositorio_eliminar.php). No aborta el guardado si esto falla —
// el Acuerdo ya quedó bien guardado, en el peor caso la precarga sigue
// apareciendo en la campanita (molesto, no destructivo).
if ($origenPrecarga && ($origenPrecarga['pos_id'] ?? null) === $posId) {
	$trimestrePrecarga = (int) ($origenPrecarga['trimestre'] ?? 0);
	$anioPrecarga = (int) ($origenPrecarga['anio'] ?? 0);
	if ($trimestrePrecarga >= 1 && $trimestrePrecarga <= 4 && $anioPrecarga > 0) {
		$stmtUsada = $mysqli->prepare(
			"UPDATE repositorio_cuota_cliente SET estado = 'usada', acuerdo_id_generado = ?
			 WHERE pos_id = ? AND trimestre = ? AND anio = ? AND estado = 'pendiente_uso'"
		);
		if ($stmtUsada) {
			$stmtUsada->bind_param('isii', $acuerdoId, $posId, $trimestrePrecarga, $anioPrecarga);
			$stmtUsada->execute();
			$stmtUsada->close();
		}
	}
}

// Snapshot del PDF: solo al generar (no en cada guardado de borrador), para
// que Historial sirva siempre "el documento tal como se generó", aunque
// después alguien edite las líneas del acuerdo. Si el render falla acá no se
// aborta la respuesta — el acuerdo ya quedó guardado bien; el próximo intento
// de verlo simplemente vuelve a caer al render en vivo de generar_acta_pdf.php.
if ($estado === 'generado') {
	try {
		$detalle = obtener_acuerdo_detalle($mysqli, $acuerdoId);
		if ($detalle) {
			$pdfBinario = generar_acta_pdf_binario($detalle);
			$tamano     = strlen($pdfBinario);
			$stmtPdf = $mysqli->prepare(
				'UPDATE repositorio_acuerdos SET pdf_documento = ?, pdf_generado_en = NOW(), pdf_tamano_bytes = ? WHERE id = ?'
			);
			if ($stmtPdf) {
				// 's' alcanza para el LONGBLOB: mysqli es binary-safe con
				// bind_param, send_long_data solo hace falta para blobs que no
				// entran en max_allowed_packet (acá son ~100-200 KB, muy lejos).
				$stmtPdf->bind_param('sii', $pdfBinario, $tamano, $acuerdoId);
				$stmtPdf->execute();
				$stmtPdf->close();
			}
		}
	} catch (\Throwable $e) {
		// No hacer nada: ver comentario de arriba.
	}
}

responder(true, 'Acuerdo guardado correctamente.', [
	'acuerdo_id'   => $acuerdoId,
	'documento_no' => $documentoNo,
	'estado'       => $estado,
]);
?>
