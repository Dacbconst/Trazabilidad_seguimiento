<?php
// Parseo de los Excel de seguimiento/liquidación de JW (Directa / Distribuidor,
// frecuencia no confirmada — el período de cada importación se detecta del
// propio archivo, no se asume) + matching
// contra repositorio_locales_supervisores_cliente y repositorio_acuerdos.
// Ver CLAUDE.md sección "Módulo Liquidación" para el contexto de negocio
// completo (por qué hay dos formatos, por qué el match no siempre es 1 a 1).
require_once __DIR__.'/xlsx_reader.php';
require_once __DIR__.'/dinero.php';

// ---------- Parseo: hoja Cuota/Venta/Rebate por categoría ----------
// Devuelve ['filas' => [...], 'mes_inicio' => 0-11, 'mes_fin' => 0-11] listas
// para insertar en repositorio_liquidacion_cuota_categoria (menos
// importacion_id/acuerdo_id) — el período NO lo elige el usuario al subir el
// archivo, se detecta leyendo directamente qué columnas de mes trae la hoja
// (ver xlsx_detectar_columnas_mes en xlsx_reader.php). Esto es a propósito:
// no hay ninguna confirmación de que JW suba esto trimestral ni mensual ni
// con ninguna frecuencia fija — el archivo real es la única fuente de verdad
// de qué período cubre cada vez.
// $canal: 'directa' o 'distribuidor' — cada uno tiene su propia hoja/columnas.
function liquidacion_parsear_cuota_categoria($rutaArchivo, $canal) {
	if ($canal === 'directa') {
		$nombreHoja = 'CUOTA CLIENTE - CATEGORÍA';
		$colCedi = 'CEDI'; $colCliente = 'CLIENTE'; $colCategoria = 'CATEGORIAS';
	} else {
		$nombreHoja = 'CUOTAS POR CAT -DISTRIBUIDORES';
		$colCedi = 'DISTRIBUIDOR'; $colCliente = 'NOMBRE'; $colCategoria = 'CATEGORIA';
	}

	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => "No se encontró la hoja \"$nombreHoja\" en el archivo."];

	// Columnas requeridas para reconocer la fila de encabezados — a propósito
	// SIN nombres de mes acá (ver comentario de arriba): un archivo que
	// reporte Enero-Marzo, o un solo mes, tiene que reconocerse igual que uno
	// que reporte Abril-Junio.
	$enc = xlsx_encontrar_encabezado($filas, [
		$colCedi, $colCliente, $colCategoria, 'REBATE $', 'REBATE MAXIMO 110%',
	]);
	if (!$enc) return ['error' => "No se pudo identificar la fila de encabezados en \"$nombreHoja\". ¿Cambió el formato del Excel?"];

	// El nombre exacto de esta columna varía entre hojas ("REBATE A APLICAR %"
	// en Directa, "REBATE" en Distribuidor) — se busca por lo que hay, no por
	// un nombre fijo, tolerando la diferencia.
	$colRebatePct = null;
	foreach (['REBATE A APLICAR %', 'REBATE'] as $candidato) {
		if (xlsx_col($enc['mapa'], $candidato) !== null) { $colRebatePct = $candidato; break; }
	}
	if ($colRebatePct === null) return ['error' => "No se encontró la columna de % de rebate en \"$nombreHoja\"."];

	// Detecta qué meses trae la hoja mirando los nombres de columna reales —
	// el bloque se repite dos veces (cuota pactada, después venta real), en
	// el mismo orden de meses las dos veces (confirmado con los 2 archivos
	// reales de datos/). Se parte a la mitad: primera mitad = cuota, segunda
	// mitad = venta.
	$columnasMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	if (count($columnasMes) < 2 || count($columnasMes) % 2 !== 0) {
		return ['error' => "No se pudieron detectar los meses de la hoja \"$nombreHoja\" (se esperaba el mismo bloque de meses repetido 2 veces: cuota y venta real)."];
	}
	$mitad = count($columnasMes) / 2;
	$colsCuota = array_slice($columnasMes, 0, $mitad);
	$colsVenta = array_slice($columnasMes, $mitad);
	$mesesCuota = array_column($colsCuota, 'mes');
	$mesesVenta = array_column($colsVenta, 'mes');
	if ($mesesCuota !== $mesesVenta) {
		return ['error' => "Los meses de cuota y de venta real de \"$nombreHoja\" no coinciden — revisar el formato del Excel a mano."];
	}
	$mesInicio = min($mesesCuota);
	$mesFin    = max($mesesCuota);
	if ($mesFin - $mesInicio + 1 !== count($mesesCuota)) {
		return ['error' => "Los meses de \"$nombreHoja\" no son consecutivos — no se puede determinar un período único para esta importación."];
	}

	$m = $enc['mapa'];
	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cedi = trim((string) ($fila[xlsx_col($m, $colCedi)] ?? ''));
		$cliente = trim((string) ($fila[xlsx_col($m, $colCliente)] ?? ''));
		$categoria = trim((string) ($fila[xlsx_col($m, $colCategoria)] ?? ''));
		// Filas vacías (huecos entre secciones, o el final de la hoja) — se saltan.
		if ($cedi === '' || $cliente === '' || $categoria === '') continue;

		// Se suman los valores mensuales detectados en vez de leer una columna
		// "TOTAL"/"CUOTA Q2" fija — ese nombre de columna también es
		// específico de trimestre y no se puede asumir. dinero_sumar() en vez
		// de + / array_sum nativo: esto es plata (ver includes/dinero.php).
		$valoresCuota = array_map(function ($c) use ($fila) { return $fila[$c['col']] ?? 0; }, $colsCuota);
		$valoresVenta = array_map(function ($c) use ($fila) { return $fila[$c['col']] ?? 0; }, $colsVenta);

		$resultado[] = [
			'cedi_o_distribuidor'  => $cedi,
			'cliente_o_nombre'     => $cliente,
			'codigo'               => $canal === 'distribuidor' ? trim((string) ($fila[xlsx_col($m, 'CODIGO')] ?? '')) ?: null : null,
			'ruc'                  => $canal === 'distribuidor' ? trim((string) ($fila[xlsx_col($m, 'RUC')] ?? '')) ?: null : null,
			'categoria'            => $categoria,
			'cuota_total_excel'    => dinero_sumar($valoresCuota),
			'venta_total_excel'    => dinero_sumar($valoresVenta),
			'rebate_pct_excel'     => (float) ($fila[xlsx_col($m, $colRebatePct)] ?? 0),
			'rebate_dolares_excel' => (float) ($fila[xlsx_col($m, 'REBATE $')] ?? 0),
			'rebate_maximo_110'    => (float) ($fila[xlsx_col($m, 'REBATE MAXIMO 110%')] ?? 0),
			'cumplimiento'         => trim((string) ($fila[xlsx_col($m, 'GANA POR CATEGORIA')] ?? $fila[xlsx_col($m, 'CUMPLIMIENTO')] ?? '')),
		];
	}
	return ['filas' => $resultado, 'mes_inicio' => $mesInicio, 'mes_fin' => $mesFin];
}

// ---------- Parseo: hoja Visibilidad ----------
function liquidacion_parsear_visibilidad($rutaArchivo, $canal) {
	if ($canal === 'directa') {
		$nombreHoja = 'VISIBILIDAD ';
		$colCedi = 'CEDI'; $colCliente = 'NOMBRES';
	} else {
		$nombreHoja = 'VISIBILIDAD (2)';
		$colCedi = 'DISTRIBUIDOR'; $colCliente = 'NOMBRE';
	}

	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => "No se encontró la hoja \"$nombreHoja\" en el archivo."];

	$enc = xlsx_encontrar_encabezado($filas, [$colCedi, $colCliente, 'CABECERA', 'ISLA', 'PERCHA']);
	if (!$enc) return ['error' => "No se pudo identificar la fila de encabezados en \"$nombreHoja\". ¿Cambió el formato del Excel?"];

	$m = $enc['mapa'];
	// CABECERA/ISLA/PERCHA se repiten 2 o 3 veces (CANTIDAD, luego PAGO, a
	// veces un tercer grupo de validación) — la 1ra ocurrencia es siempre
	// cantidad, la 2da siempre pago, en ambos formatos (confirmado leyendo
	// los dos archivos reales de datos/).
	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cedi = trim((string) ($fila[xlsx_col($m, $colCedi)] ?? ''));
		$cliente = trim((string) ($fila[xlsx_col($m, $colCliente)] ?? ''));
		if ($cedi === '' || $cliente === '') continue;

		$pagoCabecera = (float) ($fila[xlsx_col($m, 'CABECERA', 1)] ?? 0);
		$pagoIsla     = (float) ($fila[xlsx_col($m, 'ISLA', 1)] ?? 0);
		$pagoPercha   = (float) ($fila[xlsx_col($m, 'PERCHA', 1)] ?? 0);

		$resultado[] = [
			'cedi_o_distribuidor' => $cedi,
			'cliente_o_nombre'    => $cliente,
			'cantidad_cabecera'   => (int) ($fila[xlsx_col($m, 'CABECERA', 0)] ?? 0),
			'cantidad_isla'       => (int) ($fila[xlsx_col($m, 'ISLA', 0)] ?? 0),
			'cantidad_percha'     => (int) ($fila[xlsx_col($m, 'PERCHA', 0)] ?? 0),
			'pago_cabecera'       => $pagoCabecera,
			'pago_isla'           => $pagoIsla,
			'pago_percha'         => $pagoPercha,
			// dinero_sumar(), no + nativo — ver includes/dinero.php.
			'pago_total_excel'    => dinero_sumar([$pagoCabecera, $pagoIsla, $pagoPercha]),
			'cumplimiento'        => trim((string) ($fila[xlsx_col($m, 'VALIDACION')] ?? $fila[xlsx_col($m, 'CUMPLIMIENTO')] ?? '')),
		];
	}
	return ['filas' => $resultado];
}

// ---------- Matching: fila del Excel -> pos_id(s) candidato(s) ----------
// Devuelve la lista de pos_id candidatos (0, 1, o más de 1 — más de 1 es el
// caso "ARBOLEDA VACA": nombre truncado que matchea a más de un cliente real,
// ver CLAUDE.md). El llamador decide qué hacer con cada caso (1 = match
// automático, !=1 = a la cola de "pendientes de asignar").
//
// LIKE con el nombre del Excel + '%' porque el Excel trunca el nombre
// (columna angosta) — nunca al revés (nunca $posName LIKE excel%excel, el
// Excel es siempre un PREFIJO del nombre real, confirmado con datos reales).
//
// IMPORTANTE — por qué el CEDI/DISTRIBUIDOR NO es un filtro obligatorio acá:
// probado con los datos reales de datos/*.xlsx, ~50% de las filas de una
// muestra real no matcheaban filtrando por supervisor exacto, aunque el
// pos_name coincidía EXACTO — el motivo es que el supervisor/territorio de
// un cliente puede cambiar con el tiempo (reasignación), y el Excel refleja
// el supervisor de cuando se hizo, no el actual. El pos_name es un
// identificador mucho más estable que el CEDI. Por eso el match primario es
// por pos_name solo; el CEDI/DISTRIBUIDOR se usa como DESEMPATE únicamente
// cuando el pos_name truncado matchea a más de un cliente real (el caso que
// sí es genuinamente ambiguo, ej. dos "ARBOLEDA VACA ..." con distinto
// segundo nombre truncados al mismo prefijo).
function liquidacion_candidatos_pos_id($mysqli, $canal, $cediODistribuidor, $clienteONombre) {
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%')"
	);
	if (!$stmt) return [];
	$stmt->bind_param('s', $clienteONombre);
	$stmt->execute();
	$posIds = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	if (count($posIds) <= 1) return $posIds;

	// Más de un pos_id posible: intentar desempatar por CEDI (supervisor) o
	// DISTRIBUIDOR (tipo_distribuidor), según canal — con una 2da consulta
	// (no en PHP) para seguir aprovechando la collation de MySQL, que ya
	// ignora tildes/mayúsculas sola (confirmado con datos reales). Si eso
	// deja exactamente un pos_id, se usa ese; si no, se devuelven todos los
	// candidatos tal cual (sigue siendo ambiguo, va a "pendientes de asignar").
	$campo = $canal === 'directa' ? 'supervisor' : 'tipo_distribuidor';
	$stmt = $mysqli->prepare(
		"SELECT DISTINCT pos_id FROM repositorio_locales_supervisores_cliente
		 WHERE pos_name LIKE CONCAT(?, '%') AND $campo = ?"
	);
	$stmt->bind_param('ss', $clienteONombre, $cediODistribuidor);
	$stmt->execute();
	$desempatados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'pos_id');
	$stmt->close();

	return count($desempatados) === 1 ? $desempatados : $posIds;
}

// ---------- Matching: pos_id -> acuerdo_id (Acta cuyo período se solapa) ----------
// "Se solapa" y no "es exactamente igual" porque el período del Excel
// (detectado del propio archivo, ver liquidacion_parsear_cuota_categoria())
// puede no calzar 1 a 1 con el mes_inicio/mes_fin de la Acta (una Acta puede
// cubrir un rango distinto) — mismo criterio de solape que ya
// usa listar_historial_acuerdos(). $mesInicio/$mesFin en 0-11 (0=Enero).
// $anio filtra también por repositorio_acuerdos.anio (2026-08-20, decisión
// del usuario) — antes solo se filtraba por mes_inicio/mes_fin, así que un
// mismo cliente con Acta del mismo trimestre en dos años distintos (ej. Q1
// 2025 y Q1 2026) daba 2 candidatos y cualquiera de los dos caía a
// "pendiente" aunque el Año ya se elige en el formulario de subida — ahora
// se usa ese dato para no pedir resolución manual de algo que ya se sabe.
// Si hay más de un acuerdo_id posible para ese pos_id+período+año, también se
// considera "sin match único" — no hay forma de saber cuál corresponde.
function liquidacion_candidatos_acuerdo_id($mysqli, $posId, $mesInicio, $mesFin, $anio) {
	$stmt = $mysqli->prepare(
		"SELECT id FROM repositorio_acuerdos
		 WHERE pos_id = ? AND estado NOT IN ('borrador', 'anulado')
		   AND mes_inicio <= ? AND mes_fin >= ? AND anio = ?"
	);
	if (!$stmt) return [];
	$stmt->bind_param('siii', $posId, $mesFin, $mesInicio, $anio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return array_column($filas, 'id');
}

// Combina los dos pasos de match (Excel -> pos_id -> acuerdo_id) en uno.
// Devuelve ['acuerdo_id' => int, 'estado_match' => 'matcheado'] si hay
// exactamente un candidato en cada paso, o ['acuerdo_id' => null,
// 'estado_match' => 'sin_match'|'pendiente'] si no.
function liquidacion_matchear_fila($mysqli, $canal, $cediODistribuidor, $clienteONombre, $mesInicio, $mesFin, $anio) {
	$posIds = liquidacion_candidatos_pos_id($mysqli, $canal, $cediODistribuidor, $clienteONombre);
	if (count($posIds) !== 1) {
		return ['acuerdo_id' => null, 'estado_match' => count($posIds) === 0 ? 'sin_match' : 'pendiente'];
	}
	$acuerdoIds = liquidacion_candidatos_acuerdo_id($mysqli, $posIds[0], $mesInicio, $mesFin, $anio);
	if (count($acuerdoIds) !== 1) {
		return ['acuerdo_id' => null, 'estado_match' => count($acuerdoIds) === 0 ? 'sin_match' : 'pendiente'];
	}
	return ['acuerdo_id' => $acuerdoIds[0], 'estado_match' => 'matcheado'];
}

// ---------- Resumen de Pagos: junta rebate real + visibilidad por cliente ----------
// Agrupa por (cedi_o_distribuidor, cliente_o_nombre) — misma clave que usa a
// mano la hoja "RESUMEN DE PAGOS" del Excel real de JW (Volumen + Visibilidad
// = Total). NO filtra por estado_match: se muestran todos los clientes de la
// importación, con un indicador `estado` ('ok'/'revisar') según si algo de
// ese cliente quedó sin resolver en cualquiera de las 2 tablas — nunca se
// ocultan filas solo porque el match no esté completo todavía.
// SUM() de MySQL sobre columnas DECIMAL ya es aritmética exacta (a diferencia
// de sumar floats en PHP) — dinero_sumar() solo hace falta acá para el paso
// final (volumen + visibilidad), que sí se combina en PHP.
function liquidacion_calcular_resumen_pagos($mysqli, $importacionId) {
	$porCliente = [];

	$stmt = $mysqli->prepare(
		"SELECT cedi_o_distribuidor, cliente_o_nombre,
		        SUM(rebate_dolares_excel) AS volumen,
		        MAX(acuerdo_id) AS acuerdo_id,
		        SUM(estado_match <> 'matcheado') AS sin_resolver
		 FROM repositorio_liquidacion_cuota_categoria
		 WHERE importacion_id = ?
		 GROUP BY cedi_o_distribuidor, cliente_o_nombre"
	);
	$stmt->bind_param('i', $importacionId);
	$stmt->execute();
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
		$clave = $fila['cedi_o_distribuidor'].'|'.$fila['cliente_o_nombre'];
		$porCliente[$clave] = [
			'cedi_o_distribuidor' => $fila['cedi_o_distribuidor'],
			'cliente_o_nombre'    => $fila['cliente_o_nombre'],
			'volumen'             => (float) $fila['volumen'],
			'visibilidad'         => 0.0,
			'acuerdo_id'          => $fila['acuerdo_id'] !== null ? (int) $fila['acuerdo_id'] : null,
			'sin_resolver'        => (int) $fila['sin_resolver'],
		];
	}
	$stmt->close();

	$stmt = $mysqli->prepare(
		"SELECT cedi_o_distribuidor, cliente_o_nombre,
		        SUM(pago_total_excel) AS visibilidad,
		        MAX(acuerdo_id) AS acuerdo_id,
		        SUM(estado_match <> 'matcheado') AS sin_resolver
		 FROM repositorio_liquidacion_visibilidad
		 WHERE importacion_id = ?
		 GROUP BY cedi_o_distribuidor, cliente_o_nombre"
	);
	$stmt->bind_param('i', $importacionId);
	$stmt->execute();
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
		$clave = $fila['cedi_o_distribuidor'].'|'.$fila['cliente_o_nombre'];
		if (!isset($porCliente[$clave])) {
			$porCliente[$clave] = [
				'cedi_o_distribuidor' => $fila['cedi_o_distribuidor'],
				'cliente_o_nombre'    => $fila['cliente_o_nombre'],
				'volumen'             => 0.0,
				'visibilidad'         => 0.0,
				'acuerdo_id'          => null,
				'sin_resolver'        => 0,
			];
		}
		$porCliente[$clave]['visibilidad'] = (float) $fila['visibilidad'];
		if ($porCliente[$clave]['acuerdo_id'] === null && $fila['acuerdo_id'] !== null) {
			$porCliente[$clave]['acuerdo_id'] = (int) $fila['acuerdo_id'];
		}
		$porCliente[$clave]['sin_resolver'] += (int) $fila['sin_resolver'];
	}
	$stmt->close();

	// documento_no de la Acta vinculada, para trazabilidad — una sola consulta
	// con IN() en vez de una por cliente.
	$acuerdoIds = array_values(array_unique(array_filter(array_column($porCliente, 'acuerdo_id'))));
	$documentos = [];
	if ($acuerdoIds) {
		$placeholders = implode(',', array_fill(0, count($acuerdoIds), '?'));
		$stmt = $mysqli->prepare("SELECT id, documento_no FROM repositorio_acuerdos WHERE id IN ($placeholders)");
		$stmt->bind_param(str_repeat('i', count($acuerdoIds)), ...$acuerdoIds);
		$stmt->execute();
		foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $fila) {
			$documentos[(int) $fila['id']] = $fila['documento_no'];
		}
		$stmt->close();
	}

	$resultado = [];
	foreach ($porCliente as $fila) {
		$fila['total'] = dinero_sumar([$fila['volumen'], $fila['visibilidad']]);
		$fila['documento_no'] = $fila['acuerdo_id'] !== null ? ($documentos[$fila['acuerdo_id']] ?? null) : null;
		$fila['estado'] = $fila['sin_resolver'] > 0 ? 'revisar' : 'ok';
		$resultado[] = $fila;
	}
	usort($resultado, function ($a, $b) { return strcmp($a['cliente_o_nombre'], $b['cliente_o_nombre']); });
	return $resultado;
}

// ---------- Resumen de Pagos UNIFICADO por canal (2026-08-20) ----------
// Antes el Resumen de Pagos estaba atado a una sola importación — cada
// Excel trimestral que subía JW quedaba en su propia pantalla aislada, sin
// ninguna forma de ver "todo lo que llevamos" sin ir importación por
// importación (el usuario lo marcó explícitamente como un gap real, ver
// CLAUDE.md "Resumen de Pagos unificado por canal"). El usuario confirmó
// que no sabía cuál era la mejor forma de sumar montos entre trimestres
// (puede pasar que suban un Excel que mezcle pagos nuevos y viejos) — la
// decisión tomada, más segura dado eso: NUNCA sumar montos de trimestres
// distintos en un solo número. Esta función junta TODAS las importaciones
// completadas de un canal (opcionalmente filtradas por trimestre/año) en
// una sola lista, pero cada fila queda etiquetada con SU PROPIO período
// (`importacion_id`/`anio`/`mes_inicio`/`mes_fin`/`nombre_archivo`) — un
// mismo cliente que aparece en 2 trimestres da 2 filas separadas, nunca 1
// fila con el total de los dos sumado. Si en algún momento se quiere
// también un total acumulado de por vida, es un cálculo APARTE que se
// arma sobre esta misma lista (sumar por cliente ignorando período), no
// algo que esta función deba decidir por sí sola.
// $trimestre: 0 = todos, 1-4 = Q1-Q4 (mismo mapeo que trimestreABounds()
// en functions.php, que ya tiene que estar cargado por el archivo que
// llama a esta función). $anio: 0 = todos.
function liquidacion_resumen_pagos_unificado($mysqli, $canal, $trimestre, $anio) {
	$bounds = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro = $bounds ? $bounds[1] : -1;

	// Solape, no igualdad exacta: a diferencia de las Actas (siempre
	// trimestre fijo), las importaciones de Liquidación pueden cubrir
	// cualquier rango de meses (se detecta del propio Excel, no hay
	// frecuencia fija confirmada con JW, ver liquidacion_parsear_cuota_categoria())
	// — un filtro "Q1" tiene que encontrar también una importación que
	// cubra, por ejemplo, solo Febrero.
	$stmt = $mysqli->prepare(
		"SELECT id, anio, mes_inicio, mes_fin, nombre_archivo FROM repositorio_liquidacion_importaciones
		 WHERE canal = ? AND estado = 'completado'
		   AND (? = 0 OR (mes_inicio <= ? AND mes_fin >= ?))
		   AND (? = 0 OR anio = ?)
		 ORDER BY anio DESC, mes_inicio DESC"
	);
	if (!$stmt) return ['importaciones' => [], 'filas' => []];
	$stmt->bind_param('siiiii', $canal, $trimestreActivo, $mesFinFiltro, $mesInicioFiltro, $anio, $anio);
	$stmt->execute();
	$importaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();

	$filas = [];
	foreach ($importaciones as $imp) {
		foreach (liquidacion_calcular_resumen_pagos($mysqli, (int) $imp['id']) as $f) {
			$f['importacion_id'] = (int) $imp['id'];
			$f['anio'] = (int) $imp['anio'];
			$f['mes_inicio'] = (int) $imp['mes_inicio'];
			$f['mes_fin'] = (int) $imp['mes_fin'];
			$f['nombre_archivo'] = $imp['nombre_archivo'];
			$filas[] = $f;
		}
	}
	return ['importaciones' => $importaciones, 'filas' => $filas];
}
?>
