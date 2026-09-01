<?php
// Escritor de XLSX propio, sin librería externa (sin Composer en desarrollo).
// Fórmulas siempre en inglés/coma; fills 0 y 1 son "none"/"gray125" por spec OOXML.

class XlsxWriter {
	private $hojas = []; // idx => ['nombre','celdas'=>[fila][col]=>spec,'merges'=>[...]]

	// Registro deduplicado: cada combinación negrita+color+numFmt genera un solo cellXf.
	private $fonts = [['negrita' => false, 'color' => null]]; // 0 = default
	private $fills = ['none', 'gray125']; // 0 y 1 reservados por el spec, ver aviso 3
	private $estilos = [['fontIdx' => 0, 'fillIdx' => 0, 'numFmtId' => 0]]; // 0 = default
	private $estiloCache = [];

	// Convierte índice de columna 1-based a letra de Excel (1=A, 27=AA, etc).
	public static function colLetra($n) {
		$letra = '';
		while ($n > 0) {
			$resto = ($n - 1) % 26;
			$letra = chr(65 + $resto).$letra;
			$n = intdiv($n - 1, 26);
		}
		return $letra;
	}

	public function agregarHoja($nombre) {
		$this->hojas[] = ['nombre' => $nombre, 'celdas' => [], 'merges' => []];
		return count($this->hojas) - 1;
	}

	// Combina un rango de celdas (ej. "M1:O1"), para títulos de grupo fusionados.
	public function combinarCeldas($hojaIdx, $rangoRef) {
		$this->hojas[$hojaIdx]['merges'][] = $rangoRef;
	}

	// Fuerza un piso de ancho para una columna: el autofit no puede medir el
	// resultado real de una celda de fórmula, solo garantiza un mínimo.
	public function anchoMinimo($hojaIdx, $col, $ancho) {
		$this->hojas[$hojaIdx]['anchosMinimos'][$col] = $ancho;
	}

	private function fontIndex($negrita, $colorHex) {
		$colorHex = $colorHex ? strtoupper($colorHex) : null;
		foreach ($this->fonts as $i => $f) {
			if ($f['negrita'] === $negrita && $f['color'] === $colorHex) return $i;
		}
		$this->fonts[] = ['negrita' => $negrita, 'color' => $colorHex];
		return count($this->fonts) - 1;
	}

	private function fillIndex($bgHex) {
		if ($bgHex === null) return 0; // 'none'
		$bgHex = strtoupper($bgHex);
		$idx = array_search($bgHex, $this->fills, true);
		if ($idx !== false) return $idx;
		$this->fills[] = $bgHex;
		return count($this->fills) - 1;
	}

	// $numFmt: null (general) / 'money' (numFmtId 44) / 'pct' (numFmtId 10), ambos builtin de Excel.
	private function estiloId($negrita, $numFmt, $bgHex, $fontColorHex, $centrado = false) {
		$numFmtId = $numFmt === 'money' ? 44 : ($numFmt === 'pct' ? 10 : 0);
		$clave = ($negrita ? 1 : 0).'|'.$numFmtId.'|'.($bgHex ?: '').'|'.($fontColorHex ?: '').'|'.($centrado ? 1 : 0);
		if (isset($this->estiloCache[$clave])) return $this->estiloCache[$clave];
		$this->estilos[] = [
			'fontIdx' => $this->fontIndex($negrita, $fontColorHex),
			'fillIdx' => $this->fillIndex($bgHex),
			'numFmtId' => $numFmtId,
			'centrado' => $centrado,
		];
		$id = count($this->estilos) - 1;
		$this->estiloCache[$clave] = $id;
		return $id;
	}

	// $bg / $fontColor: hex sin "#" (ej. "FFC000") o null para el default.
	// $centrado: centra horizontal/vertical (OOXML alinea a la izquierda por default en celdas fusionadas).
	public function celda($hojaIdx, $fila, $col, $valor, $negrita = false, $numFmt = null, $bg = null, $fontColor = null, $centrado = false) {
		$this->hojas[$hojaIdx]['celdas'][$fila][$col] = [
			'tipo' => is_numeric($valor) ? 'n' : 's',
			'valor' => $valor,
			'estilo' => $this->estiloId($negrita, $numFmt, $bg, $fontColor, $centrado),
		];
	}

	// Funciones posteriores a Excel 2007 (ej. CONCAT) necesitan el prefijo _xlfn.
	// en el XML o Excel muestra #NAME?; IF/SUM/SUBTOTAL/VLOOKUP/AND/IFERROR no lo necesitan.
	private static $funcionesModernas = ['CONCAT'];

	private function prefijarFuncionesModernas($formula) {
		foreach (self::$funcionesModernas as $fn) {
			$formula = preg_replace('/\b'.$fn.'\(/', '_xlfn.'.$fn.'(', $formula);
		}
		return $formula;
	}

	// $formula: en inglés, separador coma, SIN el "=" inicial (ver aviso arriba).
	public function formula($hojaIdx, $fila, $col, $formula, $negrita = false, $numFmt = null, $bg = null, $fontColor = null) {
		$this->hojas[$hojaIdx]['celdas'][$fila][$col] = [
			'tipo' => 'f',
			'valor' => $this->prefijarFuncionesModernas($formula),
			'estilo' => $this->estiloId($negrita, $numFmt, $bg, $fontColor),
		];
	}

	private function escaparXml($texto) {
		return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}

	// Ancho de columna "autofit": Excel no lo calcula solo en un archivo generado
	// por código, así que se estima por caracteres del texto, clampeado en xmlCols().
	private function anchoTexto($spec) {
		if ($spec['tipo'] === 'f') {
			// Resultado de fórmula desconocido sin evaluarla: se usa un ancho razonable por formato.
			if ($spec['numFmtId'] === 44) return 13; // moneda: "$1,234,567.89"
			if ($spec['numFmtId'] === 10) return 9;  // porcentaje: "123.45%"
			return 11;
		}
		if ($spec['tipo'] === 'n') {
			if ($spec['numFmtId'] === 44) return mb_strlen(number_format((float) $spec['valor'], 2)) + 3;
			if ($spec['numFmtId'] === 10) return mb_strlen(number_format(((float) $spec['valor']) * 100, 2)) + 1;
			return mb_strlen((string) $spec['valor']);
		}
		return mb_strlen((string) $spec['valor']);
	}

	private function xmlCols(array $hoja) {
		$anchos = [];
		foreach ($hoja['celdas'] as $cols) {
			foreach ($cols as $c => $spec) {
				$numFmtId = $this->estilos[$spec['estilo']]['numFmtId'] ?? 0;
				$len = $this->anchoTexto($spec + ['numFmtId' => $numFmtId]);
				if (!isset($anchos[$c]) || $len > $anchos[$c]) $anchos[$c] = $len;
			}
		}
		// Piso manual por columna (ver anchoMinimo()), aplicado antes del clamp final.
		foreach (($hoja['anchosMinimos'] ?? []) as $c => $minimo) {
			if (!isset($anchos[$c]) || $minimo > $anchos[$c]) $anchos[$c] = $minimo;
		}
		if (!$anchos) return '';
		$xml = '<cols>';
		foreach ($anchos as $c => $len) {
			$ancho = max(8, min(45, $len + 2)); // +2 de relleno, clamp [8,45]
			$xml .= '<col min="'.$c.'" max="'.$c.'" width="'.$ancho.'" customWidth="1"/>';
		}
		return $xml.'</cols>';
	}

	private function xmlHoja(array $hoja) {
		$filas = $hoja['celdas'];
		$xml = $this->xmlCols($hoja);
		if (!$filas) {
			$xml .= '<sheetData/>';
		} else {
			$maxFila = max(array_keys($filas));
			$xml .= '<sheetData>';
			for ($f = 1; $f <= $maxFila; $f++) {
				if (empty($filas[$f])) continue;
				$xml .= '<row r="'.$f.'">';
				ksort($filas[$f]);
				foreach ($filas[$f] as $c => $spec) {
					$ref = self::colLetra($c).$f;
					$s = $spec['estilo'];
					if ($spec['tipo'] === 'f') {
						$xml .= '<c r="'.$ref.'" s="'.$s.'"><f>'.$this->escaparXml($spec['valor']).'</f></c>';
					} elseif ($spec['tipo'] === 'n') {
						$xml .= '<c r="'.$ref.'" s="'.$s.'"><v>'.$spec['valor'].'</v></c>';
					} else {
						$xml .= '<c r="'.$ref.'" t="inlineStr" s="'.$s.'"><is><t xml:space="preserve">'.$this->escaparXml($spec['valor']).'</t></is></c>';
					}
				}
				$xml .= '</row>';
			}
			$xml .= '</sheetData>';
		}
		// mergeCells va después de sheetData: el schema OOXML lo exige, si no Excel pide reparar.
		if (!empty($hoja['merges'])) {
			$xml .= '<mergeCells count="'.count($hoja['merges']).'">';
			foreach ($hoja['merges'] as $m) {
				$xml .= '<mergeCell ref="'.$m.'"/>';
			}
			$xml .= '</mergeCells>';
		}
		return $xml;
	}

	private function xmlFonts() {
		$xml = '<fonts count="'.count($this->fonts).'">';
		foreach ($this->fonts as $f) {
			$xml .= '<font><sz val="11"/><name val="Calibri"/>';
			if ($f['negrita']) $xml .= '<b/>';
			if ($f['color']) $xml .= '<color rgb="FF'.$f['color'].'"/>';
			$xml .= '</font>';
		}
		return $xml.'</fonts>';
	}

	private function xmlFills() {
		$xml = '<fills count="'.count($this->fills).'">';
		foreach ($this->fills as $f) {
			if ($f === 'none') {
				$xml .= '<fill><patternFill patternType="none"/></fill>';
			} elseif ($f === 'gray125') {
				$xml .= '<fill><patternFill patternType="gray125"/></fill>';
			} else {
				$xml .= '<fill><patternFill patternType="solid"><fgColor rgb="FF'.$f.'"/><bgColor indexed="64"/></patternFill></fill>';
			}
		}
		return $xml.'</fills>';
	}

	private function xmlCellXfs() {
		$xml = '<cellXfs count="'.count($this->estilos).'">';
		foreach ($this->estilos as $e) {
			$apply = ($e['numFmtId'] !== 0) ? ' applyNumberFormat="1"' : '';
			$centrado = !empty($e['centrado']);
			$applyAlign = $centrado ? ' applyAlignment="1"' : '';
			// borderId="1" siempre (fino en los 4 lados): este escritor solo arma tablas.
			$xml .= '<xf numFmtId="'.$e['numFmtId'].'" fontId="'.$e['fontIdx'].'" fillId="'.$e['fillIdx'].'" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"'.$apply.$applyAlign.'>';
			if ($centrado) $xml .= '<alignment horizontal="center" vertical="center"/>';
			$xml .= '</xf>';
		}
		return $xml.'</cellXfs>';
	}

	// Devuelve el .xlsx completo como string binario (header+echo o file_put_contents).
	public function generar() {
		$tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
		$zip = new ZipArchive();
		$zip->open($tmpFile, ZipArchive::OVERWRITE);

		$zip->addFromString('[Content_Types].xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
			'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
			'<Default Extension="xml" ContentType="application/xml"/>'.
			'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
			'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.
			implode('', array_map(function ($i) {
				return '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
			}, array_keys($this->hojas))).
			'</Types>'
		);

		$zip->addFromString('_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
			'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
			'</Relationships>'
		);

		$sheetsXmlWorkbook = '';
		$relsWorkbook = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		foreach ($this->hojas as $i => $hoja) {
			$sheetsXmlWorkbook .= '<sheet name="'.$this->escaparXml($hoja['nombre']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
			$relsWorkbook .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
		}

		$zip->addFromString('xl/workbook.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '.
			'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
			'<sheets>'.$sheetsXmlWorkbook.'</sheets>'.
			'</workbook>'
		);

		$zip->addFromString('xl/_rels/workbook.xml.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relsWorkbook.'</Relationships>'
		);

		$zip->addFromString('xl/styles.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
			'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
			$this->xmlFonts().
			$this->xmlFills().
			// borderId 0 = sin borde, 1 = fino negro en los 4 lados.
			'<borders count="2"><border/><border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border></borders>'.
			'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0"/></cellStyleXfs>'.
			$this->xmlCellXfs().
			'</styleSheet>'
		);

		foreach ($this->hojas as $i => $hoja) {
			$zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml',
				'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
				'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.
				$this->xmlHoja($hoja).
				'</worksheet>'
			);
		}

		$zip->close();
		$contenido = file_get_contents($tmpFile);
		unlink($tmpFile);
		return $contenido;
	}
}
?>
