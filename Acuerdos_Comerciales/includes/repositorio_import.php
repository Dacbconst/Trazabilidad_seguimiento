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
