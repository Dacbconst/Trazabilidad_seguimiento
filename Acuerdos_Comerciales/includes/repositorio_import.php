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

// Formato real confirmado por el usuario 2026-08-27 ("nuestro Excel es el
// veredicto final que es lo que quieren subir en ese repo",
// `datos/RABATE.xlsx`): CIUDAD, CANAL, CATEGORIA, SUBCATEGORIA, MARCA,
// REBATE — reemplaza por completo el primer diseño (Segmento/Sector/
// Categoría/Marca, una suposición copiada de Meta de Compras que nunca se
// confirmó con JW y nunca tuvo filas reales en producción). Su "CATEGORIA"
// es nuestro "Sector" y su "SUBCATEGORIA" es nuestra "Categoría" — mismo
// swap de vocabulario ya documentado para Meta de Compras en Registrar (ver
// CLAUDE.md "Rename de etiquetas Sector/Categoría..."). Ciudad y Canal SÍ
// importan de verdad: el mismo Sector+Categoría+Marca tiene un % de Rebate
// distinto según Canal (DISTRIBUIDOR/DIRECTA) y Ciudad (verificado con las
// 55 filas reales del archivo) — por eso son columnas propias de la tabla,
// no datos descartados.
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

	// Sector (nuestro nivel): el archivo propio dice SECTOR; el que sube JW
	// en la práctica le dice CATEGORIA.
	$colSector = null;
	foreach (['SECTOR', 'CATEGORIA'] as $candidato) {
		if (xlsx_col($m, $candidato) !== null) { $colSector = $candidato; break; }
	}

	// Categoría (nuestro nivel): el archivo propio dice CATEGORIA; JW le dice
	// SUBCATEGORIA. Si "CATEGORIA" ya quedó tomada arriba como Sector, acá
	// solo puede venir de SUBCATEGORIA — nunca la misma columna dos veces.
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
		// Fila completamente vacía (huecos entre secciones, o el final de la
		// hoja) — se salta en silencio. Una fila con SOLO alguno de los
		// campos vacío sí se incluye (queda a la vista en la previsualización
		// para que el usuario la corrija a mano, no se descarta).
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
	// SUBCATEGORIA/MARCA (2026-08-28, opcional — no todos los archivos de
	// Cuotas las traen, mismo criterio que CEDI/PLAN arriba): si están, se
	// usan en resolverProductoCuota() (functions.php) para autocompletar y
	// BLOQUEAR Categoría/Marca de la Acta Precargada en vez de depender solo
	// del historial del cliente. Si no están, xlsx_col() devuelve null y el
	// resto del pipeline sigue exactamente igual que antes.
	$colSubcategoria = xlsx_col($m, 'SUBCATEGORIA');
	$colMarca = xlsx_col($m, 'MARCA');

	$resultado = [];
	$avisos = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colCliente] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategorias] ?? '');
		if ($cliente === '' && $sector === '') continue; // fila vacía (hueco o fin de hoja)
		// "OTRAS CATEGORIAS" se ignora del todo, ni siquiera llega a la
		// previsualización (2026-08-31, pedido explícito: "solo ignóralo...
		// ya dijimos que no la usaremos") — JW confirmó que dejaron de
		// trabajar esta categoría. Filtrado acá, en el parseo, para que
		// tampoco aparezca como fila editable en la previsualización — mismo
		// criterio también aplicado como red de seguridad en
		// getters/cuotas_guardar.php, por si algo llega a saltarse este paso.
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

// Formato real confirmado por el usuario 2026-08-30 ("ya nos pasaron el
// excel... el excel es lo que definieron que piensan subir ahí
// específicamente" — `datos/PARTICIPACION PERCHA.xlsx`): CIUDAD |
// CATEGORIA | SUBCATEGORIA | MARCA | %. **CATEGORIA/SUBCATEGORIA se leen
// solo para detectar filas vacías — NUNCA se guardan** (decisión
// confirmada con el usuario, ver datos/repositorios_schema.sql: las líneas
// de Percha del Acta, a diferencia de Meta de Compras, solo guardan Marca
// — nunca habría con qué comparar esas 2 columnas). CIUDAD sí importa: la
// misma Marca puede tener % distinto por ciudad (ej. LAVA: 50% Guayaquil,
// 60% Quito, 55% "RESTO CIUDADES" — un valor real del archivo, catch-all
// para cualquier CEDI sin fila propia) — el resto de marcas usan CIUDAD
// "TODAS" (sin variación). Sin columna de Canal (a diferencia de Rebate) —
// aplica igual para Directo y Distribuidor.
function repositorio_parsear_participacion($rutaArchivo) {
	$nombreHoja = xlsx_primera_hoja($rutaArchivo);
	if ($nombreHoja === null) return ['error' => 'No se pudo abrir el archivo (¿es un .xlsx real?).'];
	$filas = xlsx_leer_hoja($rutaArchivo, $nombreHoja);
	if ($filas === null) return ['error' => 'No se pudo leer la hoja del archivo.'];

	$enc = xlsx_encontrar_encabezado($filas, ['MARCA']);
	if (!$enc) return ['error' => 'No se encontró la columna Marca en el archivo.'];
	$m = $enc['mapa'];

	// "%" a secas es el nombre real de la columna en el archivo de JW — se
	// aceptan también los nombres propios del proyecto por si se sube un
	// archivo con otro formato.
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

// Módulo "Cumplimiento de Cuota" (2026-08-30) — parsea el Excel que JW
// devuelve YA COMPLETADO (venta real + cartera cargadas a mano sobre el
// mismo archivo que se descarga desde Historial, "Descargar Excel"). A
// diferencia de todos los demás parsers de este archivo, acá NO se calcula
// nada — Cumplimiento/Gana por Categoría/Gana Total/Rebate Real Vol son
// celdas de FÓRMULA (ver getters/exportar_cuota_categoria.php) y este lector
// solo toma el valor YA CACHEADO por Excel (xlsx_leer_hoja() lee <v>, nunca
// mira <f> — ver ese archivo), tal como Excel lo calculó al guardar.
//
// Alcance de esta primera versión: solo canal Directa (hoja "CUOTA CLIENTE
// - CATEGORÍA"). El equivalente de Distribuidor ("CUOTAS POR CAT
// -DISTRIBUIDORES") tiene otro layout de columnas — mismo patrón si se pide
// después, no construido acá.
function repositorio_parsear_cumplimiento_cuota($rutaArchivo) {
	$filas = xlsx_leer_hoja($rutaArchivo, 'CUOTA CLIENTE - CATEGORÍA');
	if ($filas === null) {
		// Por si alguna herramienta reescribió el nombre de la pestaña sin tilde.
		$filas = xlsx_leer_hoja($rutaArchivo, 'CUOTA CLIENTE - CATEGORIA');
	}
	if ($filas === null) {
		return ['error' => 'No se encontró la hoja "CUOTA CLIENTE - CATEGORÍA" en el archivo. Subí el mismo Excel que se descarga desde Historial ("Descargar Excel"), ya completado.'];
	}

	$enc = xlsx_encontrar_encabezado($filas, ['CEDI', 'CLIENTE', 'CATEGORIAS', 'CUMPLIMIENTO', 'GANA POR CATEGORIA', 'GANA TOTAL', 'REBATE REAL VOL']);
	if (!$enc) {
		return ['error' => 'No se encontraron las columnas esperadas en la hoja. ¿Es el mismo archivo que se descarga desde Historial, ya completado con la venta real?'];
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

	// "TOTAL Qx"/"VENTA Qx" llevan el trimestre en el nombre (texto dinámico,
	// ver exportar_cuota_categoria.php) — no se buscan por texto, se ubican
	// por posición relativa a columnas estables, exactamente el mismo layout
	// que arma el escritor: la cuota total va justo antes de "REBATE A
	// APLICAR %", la venta total justo después de "CARTERA".
	$colCuotaTotal = ($colRebatePct !== null) ? $colRebatePct - 1 : null;
	$colVentaTotal = ($colCartera !== null) ? $colCartera + 1 : null;

	if ($colCuotaTotal === null || $colVentaTotal === null || $colPreRebate === null) {
		return ['error' => 'No se encontraron todas las columnas de resultado esperadas. ¿Es el mismo archivo que se descarga desde Historial, sin columnas movidas o borradas?'];
	}

	// El trimestre se infiere de qué meses trae el bloque de columnas de mes
	// (mismo criterio que repositorio_parsear_cuotas()) — nunca del texto
	// "Qx", que es dinámico y no se busca por nombre acá.
	$colesMes = xlsx_detectar_columnas_mes($filas[$enc['fila']]);
	$mesesDetectados = array_values(array_unique(array_map(function ($d) { return $d['mes']; }, $colesMes)));
	sort($mesesDetectados);
	$trimestres = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [9, 10, 11]];
	$trimestre = null;
	foreach ($trimestres as $idx => $meses) {
		if ($mesesDetectados === $meses) { $trimestre = $idx + 1; break; }
	}
	if ($trimestre === null) {
		return ['error' => 'No se pudo determinar el trimestre a partir de las columnas de mes del archivo.'];
	}

	$aGana = function ($v) {
		return strtoupper(trim((string) $v)) === 'GANA' ? 'gana' : 'no_gana';
	};
	$aNumero = function ($v) {
		if ($v === null || $v === '') return 0.0;
		return is_numeric($v) ? (float) $v : (float) str_replace(['$', ',', ' ', '%'], '', (string) $v);
	};

	// Cuántas veces ya se vio este cliente+CEDI+Sector en ESTE archivo — un
	// cliente puede traer 2+ filas con el mismo Sector (ej. 2 líneas de
	// "AEROSOL" con cuota distinta, misma venta real: probablemente 2
	// Subcategorías que esta hoja no distingue por nombre). `linea` (1, 2,
	// 3... en el orden en que aparecen) entra a la clave única de guardado
	// (ver cumplimiento_cuota_schema.sql) para que NINGUNA fila real se
	// pierda — antes, la 2da pisaba a la 1ra al guardar (bug real
	// encontrado 2026-08-31 con datos reales de JW). Nombrada "línea", no
	// "ocurrencia" — es un dato normal (línea 1 de 2, línea 2 de 2), no un
	// indicio de que algo falló.
	$vecesVistoSector = [];

	$resultado = [];
	for ($i = $enc['fila'] + 1; $i < count($filas); $i++) {
		$fila = $filas[$i];
		$cliente = repositorio_normalizar_texto($fila[$colCliente] ?? '');
		$sector  = repositorio_normalizar_texto($fila[$colCategorias] ?? '');
		// Fila vacía (hueco, fin de hoja) O la fila "TOTAL" del pie de tabla
		// (esa fila solo escribe CEDI='TOTAL' + fórmulas SUBTOTAL — CLIENTE y
		// CATEGORIAS nunca se llenan ahí, así que ya quedan vacíos acá solos).
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
			// CUMPLIMIENTO llega como fracción (0.1952, celda de fórmula con
			// formato 'pct') — se guarda como número de porcentaje (19.52),
			// más cómodo para mostrar sin multiplicar en el front. Rebate %
			// SÍ se deja como fracción (0.015), igual que el resto del
			// proyecto (repositorio_rebate_producto.rebate_pct).
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
	return ['filas' => $resultado, 'trimestre' => $trimestre];
}
?>
