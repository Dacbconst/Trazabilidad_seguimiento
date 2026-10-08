<?php
// Lee las columnas A y B de la primera hoja de un .xlsx con ZipArchive, sin librerías externas; también acepta .csv y .txt.

// Devuelve [textos de A, textos de B], sin vacíos. Lanza RuntimeException si el archivo no se puede leer.
function ep_xlsx_dos_columnas(string $ruta, string $extension): array {
	$columnas = ['A' => [], 'B' => []];
	if (in_array($extension, ['csv', 'txt'], true)) {
		foreach (preg_split('/\R/u', (string) file_get_contents($ruta)) as $linea) {
			$partes = explode(';', str_replace(',', ';', $linea));
			foreach (['A', 'B'] as $i => $letra) {
				$texto = trim($partes[$i] ?? '', " \t\"'");
				if ($texto !== '') {
					$columnas[$letra][] = $texto;
				}
			}
		}
		return array_values($columnas);
	}
	$zip = new ZipArchive();
	if ($zip->open($ruta) !== true) {
		throw new RuntimeException('No se pudo abrir el archivo. Usa un .xlsx, .csv o .txt.');
	}
	$compartidos = [];
	$xmlCompartidos = $zip->getFromName('xl/sharedStrings.xml');
	if ($xmlCompartidos !== false) {
		foreach (simplexml_load_string($xmlCompartidos)->si ?? [] as $si) {
			$texto = '';
			foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $t) {
				$texto .= (string) $t;
			}
			$compartidos[] = $texto;
		}
	}
	$xmlHoja = $zip->getFromName('xl/worksheets/sheet1.xml');
	$zip->close();
	if ($xmlHoja === false) {
		throw new RuntimeException('El archivo no tiene una hoja legible.');
	}
	foreach (simplexml_load_string($xmlHoja)->sheetData->row ?? [] as $fila) {
		foreach ($fila->c as $c) {
			if (!preg_match('/^([AB])\d+$/', (string) $c['r'], $m)) {
				continue;
			}
			$tipo = (string) $c['t'];
			$texto = trim($tipo === 's' ? ($compartidos[(int) $c->v] ?? '') : ($tipo === 'inlineStr' ? (string) $c->is->t : (string) $c->v));
			if ($texto !== '') {
				$columnas[$m[1]][] = $texto;
			}
		}
	}
	return array_values($columnas);
}
