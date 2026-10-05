<?php
// Genera un .xlsx real (una sola hoja, encabezado en negrita) con ZipArchive, igual que los PPTX: sin librerías externas.

function ep_xlsx_columna(int $n): string {
	$letra = '';
	while ($n > 0) {
		$resto = ($n - 1) % 26;
		$letra = chr(65 + $resto).$letra;
		$n = intdiv($n - 1, 26);
	}
	return $letra;
}

function ep_xlsx_celda(string $ref, string $texto, bool $negrita = false): string {
	$t = htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
	return '<c r="'.$ref.'" t="inlineStr"'.($negrita ? ' s="1"' : '').'><is><t xml:space="preserve">'.$t.'</t></is></c>';
}

// $encabezados: ['Col1', 'Col2', ...]; $filas: [[v1, v2, ...], ...]. Devuelve la ruta de un .xlsx temporal; el llamador lo envía y lo borra.
function ep_xlsx_generar(array $encabezados, array $filas): string {
	$destino = tempnam(sys_get_temp_dir(), 'epxlsx');
	$zip = new ZipArchive();
	$zip->open($destino, ZipArchive::OVERWRITE);

	$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
	$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
	$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Hoja1" sheetId="1" r:id="rId1"/></sheets></workbook>');
	$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
	$zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" xfId="0"/><xf numFmtId="0" fontId="1" xfId="0" applyFont="1"/></cellXfs></styleSheet>');

	$filasXml = '<row r="1">';
	foreach ($encabezados as $i => $titulo) {
		$filasXml .= ep_xlsx_celda(ep_xlsx_columna($i + 1).'1', (string) $titulo, true);
	}
	$filasXml .= '</row>';
	foreach ($filas as $f => $fila) {
		$n = $f + 2;
		$filasXml .= '<row r="'.$n.'">';
		foreach (array_values($fila) as $i => $valor) {
			$filasXml .= ep_xlsx_celda(ep_xlsx_columna($i + 1).$n, (string) $valor);
		}
		$filasXml .= '</row>';
	}
	$cols = '<cols>';
	foreach ($encabezados as $i => $titulo) {
		$cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="22" customWidth="1"/>';
	}
	$cols .= '</cols>';
	$zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.$cols.'<sheetData>'.$filasXml.'</sheetData></worksheet>');
	$zip->close();
	return $destino;
}

// Envía el .xlsx generado como descarga y borra el temporal.
function ep_xlsx_descargar(string $archivo, string $nombre): void {
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="'.$nombre.'"');
	header('Content-Length: '.filesize($archivo));
	header('Cache-Control: no-store');
	readfile($archivo);
	unlink($archivo);
}
