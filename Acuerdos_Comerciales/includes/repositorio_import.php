<?php
// Parseo de los Excel de autocarga del módulo Repositorios (Rebate,
// Participación de Percha) — self-service, subido por Michelle/Gabriela (JW),
// ver CLAUDE.md "Módulo Repositorios". A diferencia de Liquidación (formato
// fijo de JW, nombre de hoja conocido de antemano), acá el archivo puede
// venir con cualquier nombre de pestaña — se lee la PRIMERA hoja
// (xlsx_primera_hoja()) y se buscan las columnas por NOMBRE, tolerando
// variantes del nombre exacto (ej. "REBATE %" o "REBATE"), mismo criterio
// que ya usa liquidacion_import.php para columnas con nombre inconsistente
// entre archivos.
require_once __DIR__.'/xlsx_reader.php';

// Normaliza un valor de rebate/participación que puede venir como fracción
// (0.025) o como número entero de porcentaje (2.5) — Excel no distingue esto
// de forma confiable (depende de si la celda tiene formato "%" o no), así que
// se infiere por rango: nadie pacta más del 100% de rebate o participación,
// así que un valor > 1 se asume ya en unidades enteras de % y se pasa a
// fracción. Usado solo para REBATE (repositorio_rebate_producto.rebate_pct
// se guarda como fracción, igual que repositorio_acuerdo_lineas.rebate_pct).
function repositorio_valor_a_fraccion($crudo) {
	$num = is_numeric($crudo) ? (float) $crudo : (float) str_replace(['%', ','], ['', '.'], (string) $crudo);
	return $num > 1 ? $num / 100 : $num;
}

// Participación SÍ se guarda como número entero de % (repositorio_participacion_percha.participacion_pct
// DECIMAL(5,2), ej. 55.00) — el sentido inverso de repositorio_valor_a_fraccion().
function repositorio_valor_a_porcentaje($crudo) {
	$num = is_numeric($crudo) ? (float) $crudo : (float) str_replace(['%', ','], ['', '.'], (string) $crudo);
	return $num <= 1 ? $num * 100 : $num;
}

// Normaliza texto de producto (Segmento/Sector/Categoría/Marca) — mayúsculas
// + espacios de sobra colapsados. Sin esto, "Lavavajillas" y "LAVAVAJILLAS "
// (mismo producto, tipeado distinto en dos filas o en dos subidas distintas)
// generarían 2 filas separadas en vez de una sola actualizada — la clave
// única de la tabla (ver datos/repositorios_schema.sql) es exacta, no
// case-insensitive. Mismo criterio de mayúsculas que ya usa el resto del
// catálogo real (repositorio_productos, ver CLAUDE.md: "CUIDADO DEL HOGAR",
// "SPAGHETTI #5"), así que de paso queda consistente con esos datos.
function repositorio_normalizar_texto($crudo) {
	$texto = trim(preg_replace('/\s+/', ' ', (string) $crudo));
	return mb_strtoupper($texto, 'UTF-8');
}

function repositorio_parsear_rebate($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['SEGMENTO', 'SECTOR', 'CATEGORIA', 'MARCA']);
	if (!$enc) return ['error' => 'No se encontraron las columnas Segmento, Sector, Categoría y Marca en el archivo.'];

	$colRebate = null;
	foreach (['REBATE %', 'REBATE', 'REBATE PCT'] as $candidato) {
		if (xlsx_col($enc['mapa'], $candidato) !== null) { $colRebate = $candidato; break; }
	}
	if ($colRebate === null) return ['error' => 'No se encontró la columna de Rebate % en el archivo.'];

	$m = $enc['mapa'];
	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$segmento  = repositorio_normalizar_texto($fila[xlsx_col($m, 'SEGMENTO')] ?? '');
		$sector    = repositorio_normalizar_texto($fila[xlsx_col($m, 'SECTOR')] ?? '');
		$categoria = repositorio_normalizar_texto($fila[xlsx_col($m, 'CATEGORIA')] ?? '');
		$marca     = repositorio_normalizar_texto($fila[xlsx_col($m, 'MARCA')] ?? '');
		// Fila completamente vacía (huecos entre secciones, o el final de la
		// hoja) — se salta en silencio. Una fila con SOLO alguno de los 4
		// campos vacío sí se incluye (queda a la vista en la previsualización
		// para que el usuario la corrija a mano, no se descarta).
		if ($segmento === '' && $sector === '' && $categoria === '' && $marca === '') continue;
		$resultado[] = [
			'segmento'   => $segmento,
			'sector'     => $sector,
			'categoria'  => $categoria,
			'marca'      => $marca,
			'rebate_pct' => round(repositorio_valor_a_fraccion($fila[xlsx_col($m, $colRebate)] ?? 0), 4),
		];
	}
	return ['filas' => $resultado];
}

// Excel de Cuotas trimestrales por cliente (2026-08-25, ver CLAUDE.md
// "Repositorio de Cuotas trimestrales + Actas precargadas") — columnas
// reales: CEDI, CLIENTE, PLAN, CATEGORIAS, CONCAT (redundante, se ignora),
// y 3 columnas de mes, CADA UNA con su propio monto (pueden ser distintos
// entre sí — corregido 2026-08-25, la primera versión asumía mal que los 3
// meses siempre traían el mismo monto). Se devuelven como mes1/mes2/mes3
// (posición dentro del trimestre, no el índice real de mes) para que el
// modal de previsualización los edite como 3 columnas simples — el índice
// real 0-11 recién se calcula en getters/cuotas_guardar.php a partir del
// trimestre, para armar el mismo formato de `valores_mensuales` JSON que ya
// usa repositorio_acuerdo_lineas (`{"3": 600, "4": 650, "5": 700}`) — así
// la Fase 2 (Actas Precargadas) puede copiarlo directo a Meta de Compras
// sin ninguna conversión.
// A diferencia de Rebate/Participación, acá SÍ hay cliente — el pos_id se
// resuelve después, en getters/cuotas_guardar.php (necesita conexión a la
// base, este parser es puro y no recibe $mysqli).
// "CATEGORIAS" del Excel de JW es el mismo nivel que la app guarda como
// columna `sector` (ver rename de etiquetas 2026-08-25: en pantalla ahora
// se ve "Categoría", pero la columna real sigue siendo sector).
function repositorio_parsear_cuotas($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['CEDI', 'CLIENTE', 'CATEGORIAS']);
	if (!$enc) return ['error' => 'No se encontraron las columnas CEDI, CLIENTE y CATEGORIAS en el archivo.'];

	$colesMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	if (!$colesMes) return ['error' => 'No se encontró ninguna columna de mes (ej. ABRIL, MAYO, JUNIO) en el archivo.'];

	// El trimestre se infiere de qué 3 meses trae el encabezado — tienen que
	// formar exactamente uno de los 4 trimestres fijos del proyecto (mismo
	// criterio que repositorio_acuerdos.mes_inicio/mes_fin en toda la app,
	// nunca un rango libre). Si el archivo trae menos/más de 3, o meses que
	// no calzan con ningún trimestre completo, se avisa en vez de adivinar.
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

	$resultado = [];
	$avisos = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colCliente] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategorias] ?? '');
		if ($cliente === '' && $sector === '') continue; // fila vacía (hueco o fin de hoja)

		$cedi = $colCedi !== null ? repositorio_normalizar_texto($fila[$colCedi] ?? '') : '';
		$plan = $colPlan !== null ? repositorio_normalizar_texto($fila[$colPlan] ?? '') : '';

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
			'mes1'          => $valores[0] ?? 0,
			'mes2'          => $valores[1] ?? 0,
			'mes3'          => $valores[2] ?? 0,
		];
	}
	if (!$resultado) return ['error' => 'El archivo no tiene filas de datos reconocibles.'];
	return ['filas' => $resultado, 'avisos' => $avisos, 'trimestre' => $trimestre];
}

function repositorio_parsear_participacion($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['MARCA']);
	if (!$enc) return ['error' => 'No se encontró la columna Marca en el archivo.'];

	$colPart = null;
	foreach (['PARTICIPACION %', 'PARTICIPACION', 'PARTICIPACION PCT'] as $candidato) {
		if (xlsx_col($enc['mapa'], $candidato) !== null) { $colPart = $candidato; break; }
	}
	if ($colPart === null) return ['error' => 'No se encontró la columna de Participación % en el archivo.'];

	$m = $enc['mapa'];
	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$marca = repositorio_normalizar_texto($fila[xlsx_col($m, 'MARCA')] ?? '');
		if ($marca === '') continue;
		$resultado[] = [
			'marca'             => $marca,
			'participacion_pct' => round(repositorio_valor_a_porcentaje($fila[xlsx_col($m, $colPart)] ?? 0), 2),
		];
	}
	return ['filas' => $resultado];
}
?>
