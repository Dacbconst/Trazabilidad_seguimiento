<?php
// Lee la primera columna de la primera hoja de un .xlsx con ZipArchive, sin librerías externas; también acepta .csv y .txt.

// Devuelve los textos de la columna A, sin vacíos. Lanza RuntimeException si el archivo no se puede leer.
function ep_xlsx_primera_columna(string $ruta, string $extension): array {
	if (in_array($extension, ['csv', 'txt'], true)) {
		$lineas = preg_split('/\R/u', (string) file_get_contents($ruta));
		return array_values(array_filter(array_map(fn($l) => trim(explode(';', str_replace(',', ';', $l))[0], " \t\"'"), $lineas), fn($t) => $t !== ''));
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
	$valores = [];
	foreach (simplexml_load_string($xmlHoja)->sheetData->row ?? [] as $fila) {
		foreach ($fila->c as $c) {
			if (!preg_match('/^A\d+$/', (string) $c['r'])) {
				continue;
			}
			$tipo = (string) $c['t'];
			$texto = $tipo === 's' ? ($compartidos[(int) $c->v] ?? '') : ($tipo === 'inlineStr' ? (string) $c->is->t : (string) $c->v);
			if (trim($texto) !== '') {
				$valores[] = trim($texto);
			}
		}
	}
	return $valores;
}
