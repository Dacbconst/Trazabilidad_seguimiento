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
// Si hay más de un acuerdo_id posible para ese pos_id+período, también se
// considera "sin match único" — no hay forma de saber cuál corresponde.
function liquidacion_candidatos_acuerdo_id($mysqli, $posId, $mesInicio, $mesFin) {
	$stmt = $mysqli->prepare(
		"SELECT id FROM repositorio_acuerdos
		 WHERE pos_id = ? AND estado NOT IN ('borrador', 'anulado')
		   AND mes_inicio <= ? AND mes_fin >= ?"
	);
	if (!$stmt) return [];
	$stmt->bind_param('sii', $posId, $mesFin, $mesInicio);
	$stmt->execute();
	$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	return array_column($filas, 'id');
}

// Combina los dos pasos de match (Excel -> pos_id -> acuerdo_id) en uno.
// Devuelve ['acuerdo_id' => int, 'estado_match' => 'matcheado'] si hay
// exactamente un candidato en cada paso, o ['acuerdo_id' => null,
// 'estado_match' => 'sin_match'|'pendiente'] si no.
function liquidacion_matchear_fila($mysqli, $canal, $cediODistribuidor, $clienteONombre, $mesInicio, $mesFin) {
	$posIds = liquidacion_candidatos_pos_id($mysqli, $canal, $cediODistribuidor, $clienteONombre);
	if (count($posIds) !== 1) {
		return ['acuerdo_id' => null, 'estado_match' => count($posIds) === 0 ? 'sin_match' : 'pendiente'];
	}
	$acuerdoIds = liquidacion_candidatos_acuerdo_id($mysqli, $posIds[0], $mesInicio, $mesFin);
	if (count($acuerdoIds) !== 1) {
		return ['acuerdo_id' => null, 'estado_match' => count($acuerdoIds) === 0 ? 'sin_match' : 'pendiente'];
	}
	return ['acuerdo_id' => $acuerdoIds[0], 'estado_match' => 'matcheado'];
}
?>
