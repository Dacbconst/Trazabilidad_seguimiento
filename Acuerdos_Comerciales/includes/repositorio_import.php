<?php
// Parseo de Excel de autocarga del módulo Repositorios (Rebate, Participación de Percha, Cuotas) — self-service, subido por JW.
// A diferencia de Liquidación, acá se lee la PRIMERA hoja y las columnas se buscan por nombre, tolerando variantes.
require_once __DIR__.'/xlsx_reader.php';

// Normaliza rebate/participación (fracción 0.025 o entero 2.5) por rango: >1 se asume % entero y se pasa a fracción.
// Usado solo para REBATE (se guarda como fracción).
function repositorio_valor_a_fraccion($crudo) {
	$num = is_numeric($crudo) ? (float) $crudo : (float) str_replace(['%', ','], ['', '.'], (string) $crudo);
	return $num > 1 ? $num / 100 : $num;
}

// Participación se guarda como número entero de % (ej. 55.00), sentido inverso de repositorio_valor_a_fraccion().
function repositorio_valor_a_porcentaje($crudo) {
	$num = is_numeric($crudo) ? (float) $crudo : (float) str_replace(['%', ','], ['', '.'], (string) $crudo);
	return $num <= 1 ? $num * 100 : $num;
}

// Normaliza texto de producto (mayúsculas + espacios colapsados) — sin esto, "Lavavajillas" y "LAVAVAJILLAS "
// generarían 2 filas por la clave única exacta de la tabla.
function repositorio_normalizar_texto($crudo) {
	$texto = trim(preg_replace('/\s+/', ' ', (string) $crudo));
	return mb_strtoupper($texto, 'UTF-8');
}

// Columnas reales del Excel de JW: CIUDAD, CANAL, CATEGORIA, SUBCATEGORIA, MARCA, REBATE.
// Su "CATEGORIA" es nuestro Sector, su "SUBCATEGORIA" nuestra Categoría; Ciudad y Canal cambian el % de Rebate del mismo producto.
function repositorio_parsear_rebate($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['MARCA']);
	if (!$enc) return ['error' => 'No se encontró la columna Marca en el archivo.'];
	$m = $enc['mapa'];

	$colCiudad = xlsx_col($m, 'CIUDAD') !== null ? 'CIUDAD' : null;
	$colCanal  = xlsx_col($m, 'CANAL') !== null ? 'CANAL' : null;

	// Sector: el archivo propio dice SECTOR; el que sube JW le dice CATEGORIA.
	$colSector = null;
	foreach (['SECTOR', 'CATEGORIA'] as $candidato) {
		if (xlsx_col($m, $candidato) !== null) { $colSector = $candidato; break; }
	}

	// Categoría: el archivo propio dice CATEGORIA; JW le dice SUBCATEGORIA (nunca la misma columna dos veces).
	$colCategoria = null;
	foreach (['SUBCATEGORIA', 'CATEGORIA'] as $candidato) {
		if ($candidato === $colSector) continue;
		if (xlsx_col($m, $candidato) !== null) { $colCategoria = $candidato; break; }
	}

	if ($colSector === null || $colCategoria === null) {
		return ['error' => 'No se encontraron las columnas de Sector y Categoría en el archivo (se aceptan también los nombres SECTOR/CATEGORIA o, como en el formato real de JW, CATEGORIA/SUBCATEGORIA).'];
	}

	$colRebate = null;
	foreach (['REBATE %', 'REBATE', 'REBATE PCT'] as $candidato) {
		if (xlsx_col($m, $candidato) !== null) { $colRebate = $candidato; break; }
	}
	if ($colRebate === null) return ['error' => 'No se encontró la columna de Rebate % en el archivo.'];

	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$ciudad    = $colCiudad !== null ? repositorio_normalizar_texto($fila[xlsx_col($m, $colCiudad)] ?? '') : '';
		$canal     = $colCanal !== null ? repositorio_normalizar_texto($fila[xlsx_col($m, $colCanal)] ?? '') : '';
		$sector    = repositorio_normalizar_texto($fila[xlsx_col($m, $colSector)] ?? '');
		$categoria = repositorio_normalizar_texto($fila[xlsx_col($m, $colCategoria)] ?? '');
		$marca     = repositorio_normalizar_texto($fila[xlsx_col($m, 'MARCA')] ?? '');
		// Fila completamente vacía se salta; con solo algún campo vacío sí se incluye (editable en la previsualización).
		if ($ciudad === '' && $canal === '' && $sector === '' && $categoria === '' && $marca === '') continue;
		$resultado[] = [
			'ciudad'     => $ciudad,
			'canal'      => $canal,
			'sector'     => $sector,
			'categoria'  => $categoria,
			'marca'      => $marca,
			'rebate_pct' => round(repositorio_valor_a_fraccion($fila[xlsx_col($m, $colRebate)] ?? 0), 4),
		];
	}

	$aviso = ($colCiudad === null || $colCanal === null)
		? 'Este archivo no trae columna de Ciudad y/o Canal. Completalos a mano en cada fila antes de guardar. Una fila sin Ciudad o Canal no se va a poder guardar.'
		: null;

	return ['filas' => $resultado, 'aviso' => $aviso];
}

// Excel de Cuotas trimestrales por cliente: CEDI, CLIENTE, PLAN, CATEGORIAS (=nuestro `sector`), CONCAT (ignorado), 3 meses con montos independientes.
// Devuelve mes1/mes2/mes3 (posición en el trimestre); el pos_id se resuelve después en cuotas_guardar.php, este parser no recibe $mysqli.
function repositorio_parsear_cuotas($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['CEDI', 'CLIENTE', 'CATEGORIAS']);
	if (!$enc) return ['error' => 'No se encontraron las columnas CEDI, CLIENTE y CATEGORIAS en el archivo.'];

	$colesMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	if (!$colesMes) return ['error' => 'No se encontró ninguna columna de mes (ej. ABRIL, MAYO, JUNIO) en el archivo.'];

	// El trimestre se infiere de los 3 meses del encabezado — deben formar exactamente uno de los 4 trimestres fijos, si no se avisa en vez de adivinar.
	$mesesDetectados = array_map(function ($d) { return $d['mes']; }, $colesMes);
	sort($mesesDetectados);
	$trimestres = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [9, 10, 11]];
	$trimestre = null;
	foreach ($trimestres as $idx => $meses) {
		if ($mesesDetectados === $meses) { $trimestre = $idx + 1; break; }
	}
	if ($trimestre === null) {
		return ['error' => 'Las columnas de mes encontradas no forman un trimestre completo (Ene-Mar, Abr-Jun, Jul-Sep u Oct-Dic).'];
	}

	$m = $enc['mapa'];
	$colCedi = xlsx_col($m, 'CEDI');
	$colCliente = xlsx_col($m, 'CLIENTE');
	$colPlan = xlsx_col($m, 'PLAN');
	$colCategorias = xlsx_col($m, 'CATEGORIAS');
	// SUBCATEGORIA/MARCA son opcionales — si están, resolverProductoCuota() las usa para bloquear Categoría/Marca de la Acta Precargada.
	$colSubcategoria = xlsx_col($m, 'SUBCATEGORIA');
	$colMarca = xlsx_col($m, 'MARCA');

	$resultado = [];
	$avisos = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colCliente] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategorias] ?? '');
		if ($cliente === '' && $sector === '') continue; // fila vacía (hueco o fin de hoja)
		// "OTRAS CATEGORIAS" se ignora del todo (JW dejó de trabajarla) — mismo filtro también en cuotas_guardar.php como red de seguridad.
		if ($sector === 'OTRAS CATEGORIAS') continue;

		$cedi = $colCedi !== null ? repositorio_normalizar_texto($fila[$colCedi] ?? '') : '';
		$plan = $colPlan !== null ? repositorio_normalizar_texto($fila[$colPlan] ?? '') : '';
		$subcategoria = $colSubcategoria !== null ? repositorio_normalizar_texto($fila[$colSubcategoria] ?? '') : '';
		$marca = $colMarca !== null ? repositorio_normalizar_texto($fila[$colMarca] ?? '') : '';

		$valores = [];
		foreach ($colesMes as $d) {
			$crudo = $fila[$d['col']] ?? 0;
			$valores[] = round(is_numeric($crudo) ? (float) $crudo : (float) str_replace(['$', ',', ' '], '', (string) $crudo), 2);
		}

		$resultado[] = [
			'cliente_excel' => $cliente,
			'cedi_excel'    => $cedi,
			'plan'          => $plan,
			'sector'        => $sector,
			'subcategoria'  => $subcategoria,
			'marca'         => $marca,
			'mes1'          => $valores[0] ?? 0,
			'mes2'          => $valores[1] ?? 0,
			'mes3'          => $valores[2] ?? 0,
		];
	}
	if (!$resultado) return ['error' => 'El archivo no tiene filas de datos reconocibles.'];
	return ['filas' => $resultado, 'avisos' => $avisos, 'trimestre' => $trimestre];
}

// Columnas reales: CIUDAD | CATEGORIA | SUBCATEGORIA | MARCA | %. Categoria/Subcategoria solo detectan filas vacías, nunca se guardan (Percha solo guarda Marca).
// Ciudad sí importa (ej. LAVA varía por ciudad, "RESTO CIUDADES" es catch-all); sin columna de Canal, aplica igual a Directo y Distribuidor.
function repositorio_parsear_participacion($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['MARCA']);
	if (!$enc) return ['error' => 'No se encontró la columna Marca en el archivo.'];
	$m = $enc['mapa'];

	// "%" a secas es el nombre real de la columna en el Excel de JW; se aceptan también los nombres propios del proyecto.
	$colPart = null;
	foreach (['%', 'PARTICIPACION %', 'PARTICIPACION', 'PARTICIPACION PCT'] as $candidato) {
		if (xlsx_col($m, $candidato) !== null) { $colPart = $candidato; break; }
	}
	if ($colPart === null) return ['error' => 'No se encontró la columna de Participación % en el archivo.'];

	$colCiudad = xlsx_col($m, 'CIUDAD') !== null ? 'CIUDAD' : null;

	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$marca  = repositorio_normalizar_texto($fila[xlsx_col($m, 'MARCA')] ?? '');
		$ciudad = $colCiudad !== null ? repositorio_normalizar_texto($fila[xlsx_col($m, $colCiudad)] ?? '') : '';
		if ($marca === '' && $ciudad === '') continue; // fila vacía (hueco o fin de hoja)
		$resultado[] = [
			'ciudad'            => $ciudad,
			'marca'             => $marca,
			'participacion_pct' => round(repositorio_valor_a_porcentaje($fila[xlsx_col($m, $colPart)] ?? 0), 2),
		];
	}

	$aviso = $colCiudad === null
		? 'Este archivo no trae columna de Ciudad. Completala a mano en cada fila antes de guardar. Una fila sin Ciudad no se va a poder guardar.'
		: null;

	return ['filas' => $resultado, 'aviso' => $aviso];
}

// Módulo "Cumplimiento de Cuota": parsea el Excel que JW devuelve YA COMPLETADO (venta+cartera a mano sobre el export de Historial).
// No calcula nada — lee el valor ya cacheado por Excel de las celdas de fórmula. Soporta Directo y Distribuidor (funciones separadas más abajo).
function repositorio_parsear_cumplimiento_cuota($rutaArchivo) {
	// xlsx_leer_hoja() matchea el nombre de pestaña de forma tolerante (mayúsculas/tilde/espacios, ver xlsx_normalizar_nombre_hoja()).
	$filasDirecto = xlsx_leer_hoja($rutaArchivo, 'CUOTA CLIENTE - CATEGORÍA');
	if ($filasDirecto !== null) {
		return repositorio_parsear_cumplimiento_cuota_directo($filasDirecto);
	}
	$filasDistribuidor = xlsx_leer_hoja($rutaArchivo, 'CUOTAS POR CAT -DISTRIBUIDORES');
	if ($filasDistribuidor !== null) {
		return repositorio_parsear_cumplimiento_cuota_distribuidor($filasDistribuidor);
	}
	return [
		'error' => 'No se encontró la hoja.',
		'tipo' => 'hoja_no_encontrada',
		'hoja_esperada' => ['CUOTA CLIENTE - CATEGORÍA', 'CUOTAS POR CAT -DISTRIBUIDORES'],
	];
}

function repositorio_parsear_cumplimiento_cuota_directo($filas) {
	$enc = xlsx_encontrar_encabezado($filas, ['CEDI', 'CLIENTE', 'CATEGORIAS', 'CUMPLIMIENTO', 'GANA POR CATEGORIA', 'GANA TOTAL', 'REBATE REAL VOL']);
	if (!$enc) {
		return ['error' => 'Faltan columnas en la hoja.', 'tipo' => 'columnas_faltantes'];
	}
	$m = $enc['mapa'];

	$colCedi = xlsx_col($m, 'CEDI');
	$colCliente = xlsx_col($m, 'CLIENTE');
	$colPlan = xlsx_col($m, 'PLAN');
	$colCategorias = xlsx_col($m, 'CATEGORIAS');
	$colRebatePct = xlsx_col($m, 'REBATE A APLICAR %');
	$colRebateMax110 = xlsx_col($m, 'REBATE MAXIMO 110%');
	$colCartera = xlsx_col($m, 'CARTERA');
	$colCumplimiento = xlsx_col($m, 'CUMPLIMIENTO');
	$colGanaCategoria = xlsx_col($m, 'GANA POR CATEGORIA');
	$colGanaTotal = xlsx_col($m, 'GANA TOTAL');
	$colPreRebate = xlsx_col($m, 'PRE REBATE');
	$colRebateRealVol = xlsx_col($m, 'REBATE REAL VOL');

	// "TOTAL Qx"/"VENTA Qx" llevan el trimestre en el nombre (dinámico) — se ubican por posición: cuota total antes de "REBATE A APLICAR %", venta total después de "CARTERA".
	$colCuotaTotal = ($colRebatePct !== null) ? $colRebatePct - 1 : null;
	$colVentaTotal = ($colCartera !== null) ? $colCartera + 1 : null;

	if ($colCuotaTotal === null || $colVentaTotal === null || $colPreRebate === null) {
		return ['error' => 'Faltan columnas en la hoja.', 'tipo' => 'columnas_faltantes'];
	}

	// Sanity-check: si una columna se reordenó cerca de "REBATE A APLICAR %"/"CARTERA", esto leería la vecina en silencio.
	// El encabezado real en esa posición siempre empieza con "TOTAL Q"/"VENTA Q" — si no, algo se movió.
	$encCuota = xlsx_normalizar_encabezado($filas[$enc['fila']][$colCuotaTotal] ?? '');
	$encVenta = xlsx_normalizar_encabezado($filas[$enc['fila']][$colVentaTotal] ?? '');
	if (!preg_match('/^TOTAL Q\d/', $encCuota) || !preg_match('/^VENTA Q\d/', $encVenta)) {
		return ['error' => 'El orden de las columnas cambió.', 'tipo' => 'columnas_movidas', 'columnas_referencia' => ['REBATE A APLICAR %', 'CARTERA']];
	}

	// El trimestre se infiere de los meses del bloque de columnas (mismo criterio que repositorio_parsear_cuotas()), nunca del texto "Qx".
	$colesMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	$mesesDetectados = array_values(array_unique(array_map(function ($d) { return $d['mes']; }, $colesMes)));
	sort($mesesDetectados);
	$trimestres = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [9, 10, 11]];
	$trimestre = null;
	foreach ($trimestres as $idx => $meses) {
		if ($mesesDetectados === $meses) { $trimestre = $idx + 1; break; }
	}
	if ($trimestre === null) {
		return ['error' => 'No se pudo identificar el trimestre.', 'tipo' => 'trimestre_no_determinado'];
	}

	$aGana = function ($v) {
		return strtoupper(trim((string) $v)) === 'GANA' ? 'gana' : 'no_gana';
	};
	$aNumero = function ($v) {
		if ($v === null || $v === '') return 0.0;
		return is_numeric($v) ? (float) $v : (float) str_replace(['$', ',', ' ', '%'], '', (string) $v);
	};

	// Cuenta cuántas veces se vio este cliente+CEDI+Sector en el archivo — un cliente puede traer 2+ filas del mismo Sector.
	// `linea` entra a la clave única de guardado para que ninguna fila real se pierda (antes la 2da pisaba a la 1ra).
	$vecesVistoSector = [];

	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colCliente] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategorias] ?? '');
		// Fila vacía o la fila "TOTAL" del pie de tabla (CLIENTE/CATEGORIAS quedan vacíos ahí, se saltan solos).
		if ($cliente === '' && $sector === '') continue;

		$cediCruda = $colCedi !== null ? repositorio_normalizar_texto($fila[$colCedi] ?? '') : '';
		$claveLinea = $cliente.'|'.$cediCruda.'|'.$sector;
		$vecesVistoSector[$claveLinea] = ($vecesVistoSector[$claveLinea] ?? 0) + 1;

		$resultado[] = [
			'cliente_excel'     => $cliente,
			'cedi_excel'        => $cediCruda,
			'plan_excel'        => $colPlan !== null ? repositorio_normalizar_texto($fila[$colPlan] ?? '') : '',
			'sector'            => $sector,
			'linea'             => $vecesVistoSector[$claveLinea],
			'cuota_total'       => round($aNumero($fila[$colCuotaTotal] ?? 0), 2),
			'venta_total'       => round($aNumero($fila[$colVentaTotal] ?? 0), 2),
			// CUMPLIMIENTO llega como fracción (0.1952), se guarda como % (19.52); Rebate se deja como fracción (0.015), igual que el resto del proyecto.
			'cumplimiento_pct'  => round($aNumero($fila[$colCumplimiento] ?? 0) * 100, 4),
			'gana_categoria'    => $aGana($fila[$colGanaCategoria] ?? ''),
			'gana_total'        => $aGana($fila[$colGanaTotal] ?? ''),
			'rebate_pct'        => $colRebatePct !== null ? round($aNumero($fila[$colRebatePct] ?? 0), 4) : null,
			'pre_rebate'        => round($aNumero($fila[$colPreRebate] ?? 0), 2),
			'rebate_maximo_110' => $colRebateMax110 !== null ? round($aNumero($fila[$colRebateMax110] ?? 0), 2) : null,
			'rebate_real_vol'   => $colRebateRealVol !== null ? round($aNumero($fila[$colRebateRealVol] ?? 0), 2) : 0.0,
		];
	}

	if (!$resultado) return ['error' => 'El archivo no tiene filas de datos reconocibles.'];
	return ['filas' => $resultado, 'trimestre' => $trimestre, 'canal_detectado' => 'directo'];
}

// Canal Distribuidor, hoja "CUOTAS POR CAT -DISTRIBUIDORES" — mismo criterio que Directo, layout distinto: NOMBRE/CIUDAD/CATEGORIA/REBATE, sin CARTERA.
// "DISTRIBUIDOR" (empresa) se guarda en `plan_excel`, mismo campo que Directo usa para PLAN.
function repositorio_parsear_cumplimiento_cuota_distribuidor($filas) {
	$enc = xlsx_encontrar_encabezado($filas, ['CIUDAD', 'NOMBRE', 'CATEGORIA', 'CUMPLIMIENTO', 'GANA POR CATEGORIA', 'GANA TOTAL Q', 'REBATE REAL VOL']);
	if (!$enc) {
		return ['error' => 'Faltan columnas en la hoja.', 'tipo' => 'columnas_faltantes'];
	}
	$m = $enc['mapa'];

	$colDistribuidor = xlsx_col($m, 'DISTRIBUIDOR');
	$colCiudad = xlsx_col($m, 'CIUDAD');
	$colNombre = xlsx_col($m, 'NOMBRE');
	$colCategoria = xlsx_col($m, 'CATEGORIA');
	$colRebatePct = xlsx_col($m, 'REBATE');
	$colRebateMax110 = xlsx_col($m, 'REBATE MAXIMO 110%');
	$colCumplimiento = xlsx_col($m, 'CUMPLIMIENTO');
	$colGanaCategoria = xlsx_col($m, 'GANA POR CATEGORIA');
	$colGanaTotal = xlsx_col($m, 'GANA TOTAL Q');
	$colPreRebate = xlsx_col($m, 'PRE REBATE');
	$colRebateRealVol = xlsx_col($m, 'REBATE REAL VOL');

	// "CUOTA Qx"/"TOTAL VENTA Qx" se ubican por posición: cuota total antes de "REBATE", venta total antes de "CUMPLIMIENTO" (sin ancla CARTERA acá).
	$colCuotaTotal = ($colRebatePct !== null) ? $colRebatePct - 1 : null;
	$colVentaTotal = ($colCumplimiento !== null) ? $colCumplimiento - 1 : null;

	if ($colCuotaTotal === null || $colVentaTotal === null || $colPreRebate === null) {
		return ['error' => 'Faltan columnas en la hoja.', 'tipo' => 'columnas_faltantes'];
	}

	$encCuota = xlsx_normalizar_encabezado($filas[$enc['fila']][$colCuotaTotal] ?? '');
	$encVenta = xlsx_normalizar_encabezado($filas[$enc['fila']][$colVentaTotal] ?? '');
	if (!preg_match('/^CUOTA Q\d/', $encCuota) || !preg_match('/^TOTAL VENTA Q\d/', $encVenta)) {
		return ['error' => 'El orden de las columnas cambió.', 'tipo' => 'columnas_movidas', 'columnas_referencia' => ['REBATE', 'CUMPLIMIENTO']];
	}

	$colesMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	$mesesDetectados = array_values(array_unique(array_map(function ($d) { return $d['mes']; }, $colesMes)));
	sort($mesesDetectados);
	$trimestres = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [9, 10, 11]];
	$trimestre = null;
	foreach ($trimestres as $idx => $meses) {
		if ($mesesDetectados === $meses) { $trimestre = $idx + 1; break; }
	}
	if ($trimestre === null) {
		return ['error' => 'No se pudo identificar el trimestre.', 'tipo' => 'trimestre_no_determinado'];
	}

	$aGana = function ($v) {
		return strtoupper(trim((string) $v)) === 'GANA' ? 'gana' : 'no_gana';
	};
	$aNumero = function ($v) {
		if ($v === null || $v === '') return 0.0;
		return is_numeric($v) ? (float) $v : (float) str_replace(['$', ',', ' ', '%'], '', (string) $v);
	};

	$vecesVistoSector = [];
	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colNombre] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategoria] ?? '');
		if ($cliente === '' && $sector === '') continue;

		$cediCruda = $colCiudad !== null ? repositorio_normalizar_texto($fila[$colCiudad] ?? '') : '';
		$claveLinea = $cliente.'|'.$cediCruda.'|'.$sector;
		$vecesVistoSector[$claveLinea] = ($vecesVistoSector[$claveLinea] ?? 0) + 1;

		$resultado[] = [
			'cliente_excel'     => $cliente,
			'cedi_excel'        => $cediCruda,
			'plan_excel'        => $colDistribuidor !== null ? repositorio_normalizar_texto($fila[$colDistribuidor] ?? '') : '',
			'sector'            => $sector,
			'linea'             => $vecesVistoSector[$claveLinea],
			'cuota_total'       => round($aNumero($fila[$colCuotaTotal] ?? 0), 2),
			'venta_total'       => round($aNumero($fila[$colVentaTotal] ?? 0), 2),
			'cumplimiento_pct'  => round($aNumero($fila[$colCumplimiento] ?? 0) * 100, 4),
			'gana_categoria'    => $aGana($fila[$colGanaCategoria] ?? ''),
			'gana_total'        => $aGana($fila[$colGanaTotal] ?? ''),
			'rebate_pct'        => $colRebatePct !== null ? round($aNumero($fila[$colRebatePct] ?? 0), 4) : null,
			'pre_rebate'        => round($aNumero($fila[$colPreRebate] ?? 0), 2),
			'rebate_maximo_110' => $colRebateMax110 !== null ? round($aNumero($fila[$colRebateMax110] ?? 0), 2) : null,
			'rebate_real_vol'   => $colRebateRealVol !== null ? round($aNumero($fila[$colRebateRealVol] ?? 0), 2) : 0.0,
		];
	}

	if (!$resultado) return ['error' => 'El archivo no tiene filas de datos reconocibles.'];
	return ['filas' => $resultado, 'trimestre' => $trimestre, 'canal_detectado' => 'distribuidor'];
}
?>
