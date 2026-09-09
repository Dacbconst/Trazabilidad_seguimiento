<?php
// Lector de XLSX mínimo y propio, sin PhpSpreadsheet (pesado, problemas subiendo vendor/ grande por FTP/WinSCP). Un .xlsx es un ZIP con XML adentro: solo necesita la extensión `zip` + SimpleXML, ambas comunes en hosting compartido.

function xlsx_disponible() {
	return class_exists('ZipArchive');
}

// Excel guarda los textos en una tabla compartida (sharedStrings.xml); hay que resolverla una vez por archivo antes de leer cualquier hoja.
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

// Mapea nombre de hoja -> ruta interna del XML: workbook.xml solo tiene nombre + r:id, el .rels conecta ese r:id con el archivo real.
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
			// Target puede venir relativo a xl/ (Excel) o absoluto al paquete (openpyxl/Google Sheets); sin normalizar, la ruta queda duplicada y la hoja "no se encuentra".
			$target = $ridToTarget[$rid];
			$mapa[$nombre] = (strpos($target, '/') === 0) ? ltrim($target, '/') : 'xl/'.$target;
		}
	}
	return $mapa;
}

// Nombre de la primera hoja en el orden real de las pestañas, para lectores que no conocen un nombre fijo de antemano (ver includes/repositorio_import.php).
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

// Devuelve un array de filas, cada fila indexada 0-based por columna, respetando huecos (Excel no siempre escribe <c> para celdas vacías).
function xlsx_leer_hoja($rutaArchivo, $nombreHoja) {
	if (!xlsx_disponible()) return null;

	$zip = new ZipArchive();
	if ($zip->open($rutaArchivo) !== true) return null;

	$strings = xlsx_leer_shared_strings($zip);
	$mapaHojas = xlsx_mapa_hojas($zip);

	// Match tolerante del nombre de pestaña (mayúsculas/tildes/espacios de más), no comparación exacta — mismo criterio que xlsx_encontrar_encabezado().
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
			// inlineStr NUNCA trae <v> (el texto vive en <is><t>); hay que chequear ese tipo antes de descartar por "sin <v>", si no vuelve null en silencio.
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
				// numérico (o fecha serial de Excel, se deja como número — no hace falta convertir fechas para este importador)
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

// Busca la primera fila (de las primeras $maxFilas) que contiene TODAS las columnas requeridas: los Excel de JW tienen filas/títulos vacíos arriba.
function xlsx_normalizar_encabezado($texto) {
	$texto = trim((string) $texto);
	$texto = mb_strtoupper($texto, 'UTF-8');
	$texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $texto);
	return $texto;
}

// Mismo criterio que xlsx_normalizar_encabezado(), para el NOMBRE DE LA PESTAÑA: además colapsa espacios de más, común en nombres retipeados a mano.
function xlsx_normalizar_nombre_hoja($texto) {
	$texto = xlsx_normalizar_encabezado($texto);
	return preg_replace('/\s+/', ' ', $texto);
}

// $mapa[NOMBRE] es un ARRAY de índices: los reportes de JW repiten el mismo mes 2 veces (cuota pactada, venta real); un solo índice pisaría la 1ra ocurrencia. Usar xlsx_col($mapa, 'ABRIL', 0) para la 1ra, 1 para la 2da, etc.
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

// Índice de columna para la N-ésima ocurrencia (0-based) de un nombre de columna repetido — ver comentario de xlsx_encontrar_encabezado().
function xlsx_col(array $mapa, $nombre, $ocurrencia = 0) {
	$nombre = xlsx_normalizar_encabezado($nombre);
	return $mapa[$nombre][$ocurrencia] ?? null;
}

// 0=Enero...11=Diciembre, igual que mes_inicio/mes_fin de repositorio_acuerdos. Nunca hardcodear 3 meses asumiendo que el reporte siempre es Q2.
function xlsx_meses_nombres() {
	return ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];
}

// Devuelve [['mes' => 0-11, 'col' => int], ...] en orden de aparición. No agrupa por nombre: los reportes repiten el bloque de meses 2 veces (cuota pactada, venta real), quien llama decide cómo partir el resultado (ver liquidacion_parsear_cuota_categoria()).
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
