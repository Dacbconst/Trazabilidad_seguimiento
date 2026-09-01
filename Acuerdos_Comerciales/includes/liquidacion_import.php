<?php
// Parseo de Excel de liquidacion de JW (Directa/Distribuidor) + matching contra
// repositorio_locales_supervisores_cliente y repositorio_acuerdos. Ver CLAUDE.md "Modulo Liquidacion".
require_once __DIR__.'/xlsx_reader.php';
require_once __DIR__.'/dinero.php';

// ---------- Parseo: hoja Cuota/Venta/Rebate por categoria ----------
// Detecta el periodo leyendo las columnas de mes reales de la hoja (no asume frecuencia fija).
// $canal: 'directa' o 'distribuidor', cada uno con su propia hoja/columnas.
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

	// Columnas requeridas para reconocer encabezados, sin nombres de mes (el periodo varia).
	$enc = xlsx_encontrar_encabezado($filas, [
		$colCedi, $colCliente, $colCategoria, 'REBATE $', 'REBATE MAXIMO 110%',
	]);
	if (!$enc) return ['error' => "No se pudo identificar la fila de encabezados en \"$nombreHoja\". ¿Cambió el formato del Excel?"];

	// Nombre de columna varia entre hojas ("REBATE A APLICAR %" en Directa, "REBATE" en Distribuidor).
	$colRebatePct = null;
	foreach (['REBATE A APLICAR %', 'REBATE'] as $candidato) {
		if (xlsx_col($enc['mapa'], $candidato) !== null) { $colRebatePct = $candidato; break; }
	}
	if ($colRebatePct === null) return ['error' => "No se encontró la columna de % de rebate en \"$nombreHoja\"."];

	// El bloque de meses se repite 2 veces en la hoja (cuota pactada, luego venta real), mismo orden.
	// Se parte la lista a la mitad: primera mitad = cuota, segunda mitad = venta.
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

		// Se suman los meses detectados en vez de leer una columna "TOTAL" fija (el nombre varia por periodo).
		// dinero_sumar() en vez de +/array_sum nativo, es plata (ver includes/dinero.php).
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
	// CABECERA/ISLA/PERCHA se repiten 2-3 veces en la hoja: 1ra ocurrencia = cantidad, 2da = pago.
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
// Match primario por pos_name LIKE 'excel%' (el Excel trunca el nombre, siempre es prefijo).
// CEDI/DISTRIBUIDOR NO filtra el match (el supervisor de un cliente cambia con el tiempo, pos_name es mas estable); solo desempata cuando el prefijo matchea a mas de un cliente.
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

	// Desempate por CEDI/DISTRIBUIDOR via una 2da consulta SQL (aprovecha la collation de MySQL,
	// ignora tildes/mayusculas). Si no deja exactamente 1 pos_id, se devuelven todos (sigue ambiguo).
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

// ---------- Matching: pos_id -> acuerdo_id (Acta cuyo periodo se solapa) ----------
// Se solapa, no es exactamente igual, porque el periodo del Excel puede no calzar 1 a 1
// con mes_inicio/mes_fin de la Acta. Tambien filtra por anio, para no confundir el mismo trimestre de anios distintos.
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

// Combina los 2 pasos de match (Excel -> pos_id -> acuerdo_id): 'matcheado' si hay
// exactamente 1 candidato en cada paso, si no 'sin_match'/'pendiente'.
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
// Agrupa por (cedi_o_distribuidor, cliente_o_nombre). No filtra por estado_match: siempre
// muestra todos los clientes, con `estado` ('ok'/'revisar') si algo quedo sin resolver.
function liquidacion_calcular_resumen_pagos($mysqli, $importacionId) {
	$porCliente = [];

	$stmt = $mysqli->prepare(
		"SELECT cedi_o_distribuidor, cliente_o_nombre,
		        SUM(rebate_dolares_excel) AS volumen,
		        MAX(acuerdo_id) AS acuerdo_id,
		        SUM(estado_match NOT IN ('matcheado', 'sin_acta')) AS sin_resolver
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
		        SUM(estado_match NOT IN ('matcheado', 'sin_acta')) AS sin_resolver
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

	// documento_no de la Acta vinculada (una sola consulta con IN(), no una por cliente).
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

// ---------- Resumen de Pagos UNIFICADO por canal ----------
// Junta todas las importaciones completadas de un canal; cada fila mantiene SU PROPIO periodo,
// nunca se suman montos de trimestres distintos. $trimestre: 0=todos, 1-4=Q1-Q4. $anio: 0=todos.
function liquidacion_resumen_pagos_unificado($mysqli, $canal, $trimestre, $anio) {
	$bounds = trimestreABounds($trimestre);
	$trimestreActivo = $bounds ? 1 : 0;
	$mesInicioFiltro = $bounds ? $bounds[0] : -1;
	$mesFinFiltro = $bounds ? $bounds[1] : -1;

	// Solape, no igualdad exacta: una importacion puede cubrir cualquier rango de meses,
	// un filtro "Q1" debe encontrar tambien una que cubra, por ejemplo, solo Febrero.
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
