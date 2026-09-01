<?php
// Lector de XLSX mínimo y propio — sin librería externa (PhpSpreadsheet es
// pesado y este proyecto ya tuvo problemas subiendo carpetas de vendor/
// grandes por FTP/WinSCP, ver CLAUDE.md). Un .xlsx es un ZIP con XML adentro:
// esto solo necesita la extensión `zip` de PHP (muy común en hosting
// compartido) + SimpleXML (siempre viene con PHP). Alcanza para lo que hace
// falta acá: leer hojas puntuales por nombre y devolver filas de celdas.

function xlsx_disponible() {
	return class_exists('ZipArchive');
}

// Excel guarda los textos en una tabla compartida (sharedStrings.xml) y cada
// celda de texto solo referencia un índice a esa tabla — hay que resolverla
// una vez por archivo antes de poder leer cualquier hoja.
function xlsx_leer_shared_strings(ZipArchive $zip) {
	$strings = [];
	$xml = $zip->getFromName('xl/sharedStrings.xml');
	if ($xml === false) return $strings;
	$sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	if (!$sx) return $strings;
	foreach ($sx->si as $si) {
		// <si> puede tener texto simple <t> o texto con formato en varios <r><t>.
		if (isset($si->t)) {
			$strings[] = (string) $si->t;
		} else {
			$texto = '';
			foreach ($si->r as $r) $texto .= (string) $r->t;
			$strings[] = $texto;
		}
	}
	return $strings;
}

// Mapea nombre de hoja (el que se ve en las pestañas de Excel) -> ruta interna
// del XML (xl/worksheets/sheetN.xml) — el workbook.xml solo tiene el nombre y
// un r:id, y el .rels es el que conecta ese r:id con el archivo real.
function xlsx_mapa_hojas(ZipArchive $zip) {
	$workbookXml = $zip->getFromName('xl/workbook.xml');
	$relsXml     = $zip->getFromName('xl/_rels/workbook.xml.rels');
	if ($workbookXml === false || $relsXml === false) return [];

	$wb   = simplexml_load_string($workbookXml);
	$rels = simplexml_load_string($relsXml);

	$ridToTarget = [];
	foreach ($rels->Relationship as $rel) {
		$ridToTarget[(string) $rel['Id']] = (string) $rel['Target'];
	}

	$mapa = [];
	$nsR = $wb->sheets->sheet[0]->attributes('r', true); // namespace r:id
	foreach ($wb->sheets->sheet as $sheet) {
		$rid = (string) $sheet->attributes('r', true)->id;
		$nombre = (string) $sheet['name'];
		if (isset($ridToTarget[$rid])) {
			// El Target del .rels puede venir relativo a xl/ ("worksheets/sheet2.xml",
			// lo que escribe Excel de escritorio) o absoluto al paquete
			// ("/xl/worksheets/sheet2.xml", lo que escribe openpyxl y algunas
			// exportaciones de Google Sheets/LibreOffice) — ambos son válidos según
			// el spec de OOXML. Si no se normaliza el caso absoluto, la ruta queda
			// duplicada ("xl/xl/worksheets/..."), la hoja no se encuentra y el
			// importador falla con "No se encontró la hoja" aunque el nombre esté bien.
			$target = $ridToTarget[$rid];
			$mapa[$nombre] = (strpos($target, '/') === 0) ? ltrim($target, '/') : 'xl/'.$target;
		}
	}
	return $mapa;
}

// Nombre de la primera hoja del archivo, en el orden real de las pestañas
// (los `array` de PHP preservan orden de inserción, y xlsx_mapa_hojas()
// inserta en el mismo orden que <sheets><sheet> del workbook.xml) — para
// lectores que no conocen un nombre de hoja fijo de antemano (a diferencia
// de Liquidación, que sí conoce el nombre exacto que usa JW), ver
// includes/repositorio_import.php.
function xlsx_primera_hoja($rutaArchivo) {
	if (!xlsx_disponible()) return null;
	$zip = new ZipArchive();
	if ($zip->open($rutaArchivo) !== true) return null;
	$mapa = xlsx_mapa_hojas($zip);
	$zip->close();
	$nombres = array_keys($mapa);
	return $nombres ? $nombres[0] : null;
}

// Convierte "AB" (letras de columna Excel) -> índice 0-based.
function xlsx_col_a_indice($letras) {
	$letras = preg_replace('/[0-9]/', '', $letras);
	$indice = 0;
	for ($i = 0; $i < strlen($letras); $i++) {
		$indice = $indice * 26 + (ord($letras[$i]) - ord('A') + 1);
	}
	return $indice - 1;
}

// Lee una hoja completa por nombre y devuelve un array de filas, cada fila un
// array de celdas indexado 0-based por columna (respeta celdas vacías/huecos,
// necesario porque Excel no siempre escribe <c> para celdas sin valor).
function xlsx_leer_hoja($rutaArchivo, $nombreHoja) {
	if (!xlsx_disponible()) return null;

	$zip = new ZipArchive();
	if ($zip->open($rutaArchivo) !== true) return null;

	$strings = xlsx_leer_shared_strings($zip);
	$mapaHojas = xlsx_mapa_hojas($zip);

	// Match tolerante del NOMBRE de la pestaña (2026-08-31, pedido explícito):
	// antes era `isset($mapaHojas[$nombreHoja])`, una comparación exacta —
	// alguien que tipeó la pestaña en minúsculas, sin tilde, o con un espacio
	// de más ("Cuota Cliente - Categoria ") hacía fallar la búsqueda entera,
	// aunque el nombre fuera "el mismo" a simple vista. Mismo criterio que ya
	// usa xlsx_encontrar_encabezado() para los ENCABEZADOS de columna (ver
	// xlsx_normalizar_nombre_hoja() más abajo) — acá se aplica igual al
	// nombre de la hoja en sí.
	$rutaXml = null;
	foreach ($mapaHojas as $nombreReal => $ruta) {
		if (xlsx_normalizar_nombre_hoja($nombreReal) === xlsx_normalizar_nombre_hoja($nombreHoja)) {
			$rutaXml = $ruta;
			break;
		}
	}
	if ($rutaXml === null) {
		$zip->close();
		return null;
	}

	$sheetXml = $zip->getFromName($rutaXml);
	$zip->close();
	if ($sheetXml === false) return null;

	$sx = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_NOCDATA);
	if (!$sx) return null;

	$filas = [];
	foreach ($sx->sheetData->row as $row) {
		$fila = [];
		foreach ($row->c as $c) {
			$ref = (string) $c['r']; // ej. "C5"
			$col = xlsx_col_a_indice($ref);
			$tipo = (string) $c['t'];
			// Las celdas inlineStr NUNCA traen <v> (el texto vive en <is><t>) —
			// hay que revisar ese tipo ANTES de descartar por "sin <v>", si no
			// esta rama queda inalcanzable y toda celda de texto en ese formato
			// vuelve null en silencio. Excel de escritorio casi siempre usa
			// sharedStrings (t="s"), pero openpyxl y otras herramientas escriben
			// inlineStr — ambos son válidos según el spec de OOXML.
			if ($tipo === 'inlineStr') {
				$valor = isset($c->is->t) ? (string) $c->is->t : '';
				$fila[$col] = $valor;
				continue;
			}
			$valorCrudo = isset($c->v) ? (string) $c->v : null;

			if ($valorCrudo === null) {
				$valor = null;
			} elseif ($tipo === 's') {
				// índice a sharedStrings
				$valor = $strings[(int) $valorCrudo] ?? '';
			} else {
				// numérico (o fecha serial de Excel, se deja como número —
				// no hace falta convertir fechas para este importador)
				$valor = is_numeric($valorCrudo) ? $valorCrudo + 0 : $valorCrudo;
			}
			$fila[$col] = $valor;
		}
		if ($fila) {
			$maxCol = max(array_keys($fila));
			for ($i = 0; $i <= $maxCol; $i++) {
				if (!array_key_exists($i, $fila)) $fila[$i] = null;
			}
			ksort($fila);
		}
		$filas[] = array_values($fila);
	}

	return $filas;
}

// Busca la fila de encabezados dentro de las primeras $maxFilas (los Excel de
// JW tienen filas vacías/títulos de grupo arriba del encabezado real, ver
// CLAUDE.md) — la identifica como la primera fila que contiene TODAS las
// columnas requeridas (comparación case-insensitive, sin tildes ni espacios
// de sobra, porque el cliente no siempre tipea igual entre trimestres).
function xlsx_normalizar_encabezado($texto) {
	$texto = trim((string) $texto);
	$texto = mb_strtoupper($texto, 'UTF-8');
	$texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $texto);
	return $texto;
}

// Mismo criterio que xlsx_normalizar_encabezado() (mayúsculas, sin tildes),
// pero para el NOMBRE DE LA PESTAÑA en sí (usado por xlsx_leer_hoja()) — acá
// además colapsa espacios de más ("Cuota  Cliente" -> "Cuota Cliente"), algo
// que no hacía falta para encabezados de columna (celdas sueltas, casi nunca
// con doble espacio) pero sí es común en el nombre de una pestaña retipeado
// a mano.
function xlsx_normalizar_nombre_hoja($texto) {
	$texto = xlsx_normalizar_encabezado($texto);
	return preg_replace('/\s+/', ' ', $texto);
}

// $mapa[NOMBRE] es un ARRAY de índices (no un solo índice): los reportes de
// JW repiten "ABRIL"/"MAYO"/"JUNIO" dos veces en la misma hoja (una vez para
// la cuota pactada, otra para la venta real, distinguidas solo por una fila
// de rótulos de grupo arriba, ej. " VENTA Q2 2026") — quedarse con un solo
// índice por nombre pisaría la primera ocurrencia con la segunda. Usar
// xlsx_col($mapa, 'ABRIL', 0) para la 1ra ocurrencia, 1 para la 2da, etc.
function xlsx_encontrar_encabezado(array $filas, array $columnasRequeridas, $maxFilas = 10) {
	$requeridas = array_map('xlsx_normalizar_encabezado', $columnasRequeridas);
	$limite = min($maxFilas, count($filas));
	for ($i = 0; $i < $limite; $i++) {
		$normalizada = array_map('xlsx_normalizar_encabezado', $filas[$i]);
		$faltantes = array_diff($requeridas, $normalizada);
		if (!$faltantes) {
			$mapa = [];
			foreach ($normalizada as $col => $nombre) {
				if ($nombre !== '') $mapa[$nombre][] = $col;
			}
			return ['fila' => $i, 'mapa' => $mapa];
		}
	}
	return null;
}

// Índice de columna para la N-ésima ocurrencia (0-based) de un nombre de
// columna repetido — ver comentario de xlsx_encontrar_encabezado().
function xlsx_col(array $mapa, $nombre, $ocurrencia = 0) {
	$nombre = xlsx_normalizar_encabezado($nombre);
	return $mapa[$nombre][$ocurrencia] ?? null;
}

// 0=Enero...11=Diciembre, mismo criterio que mes_inicio/mes_fin de
// repositorio_acuerdos en toda la app — nunca hardcodear "ABRIL"/"MAYO"/
// "JUNIO" pensando que el reporte siempre es Q2 (bug real que hubo acá:
// el primer lector solo reconocía esos 3 meses, así que un archivo de otro
// período ni siquiera encontraba el encabezado).
function xlsx_meses_nombres() {
	return ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
}

// Recorre una fila de encabezado (ya normalizada por posición, ver
// xlsx_encontrar_encabezado) buscando nombres de mes, en el orden en que
// aparecen de izquierda a derecha. Devuelve [['mes' => 0-11, 'col' => int], ...].
// Los reportes de JW repiten el mismo bloque de meses dos veces (cuota
// pactada, después venta real) — por eso esto no agrupa por nombre de mes,
// devuelve CADA aparición en orden, y quien llama decide cómo partir el
// resultado en dos mitades (ver liquidacion_parsear_cuota_categoria()).
function xlsx_detectar_columnas_mes(array $filaEncabezado) {
	$meses = xlsx_meses_nombres();
	$mesesNormalizados = array_flip(array_map('xlsx_normalizar_encabezado', $meses));
	$detectados = [];
	foreach ($filaEncabezado as $col => $valor) {
		$normalizado = xlsx_normalizar_encabezado($valor);
		if (isset($mesesNormalizados[$normalizado])) {
			$detectados[] = ['mes' => $mesesNormalizados[$normalizado], 'col' => $col];
		}
	}
	return $detectados;
}
?>
