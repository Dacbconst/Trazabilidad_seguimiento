<?php
// Tablas nativas de PowerPoint con el estilo de las tablas de Epson: franja con el título, cabecera azul y cuerpo sin bordes.

const EP_PPT_TABLA_AZUL = '215F9A';
const EP_PPT_TABLA_FILA = 200000; // alto de fila en EMU
const EP_PPT_TABLA_FUENTE = 9;
const EP_PPT_TABLA_SEPARACION = 300000; // espacio entre las dos columnas de tabla de una diapositiva

// Cuántas filas de datos entran en un bloque de alto $alto (descontando franja doble y cabecera de hasta dos líneas).
function ep_ppt_tabla_capacidad(int $alto): int {
	return max(1, intdiv($alto, EP_PPT_TABLA_FILA) - 4);
}

// Cambia el recuadro $rect por 1 o 2 bloques de tabla lado a lado; $columnas = [[título, fracción, alineación]], cada bloque = [['celdas', 'negrita']].
function ep_ppt_tabla_en_recuadro(DOMDocument $dom, DOMXPath $xp, string $rect, string $titulo, array $columnas, array $bloques): void {
	$forma = ep_ppt_forma($xp, $rect);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	$ext = $forma ? $xp->query('.//a:xfrm/a:ext', $forma)->item(0) : null;
	if (!$off || !$ext) {
		return;
	}
	[$x, $y, $cx] = [(int) $off->getAttribute('x'), (int) $off->getAttribute('y'), (int) $ext->getAttribute('cx')];
	// Un solo bloque va centrado a dos tercios del ancho, como en el formato; dos bloques se reparten el ancho.
	$ancho = count($bloques) > 1 ? intdiv($cx - EP_PPT_TABLA_SEPARACION, 2) : (int) round($cx * 0.66);
	$x0 = count($bloques) > 1 ? $x : $x + intdiv($cx - $ancho, 2);
	foreach (array_values($bloques) as $i => $filas) {
		$xml = ep_ppt_tabla_xml($titulo, $columnas, $filas, $x0 + $i * ($ancho + EP_PPT_TABLA_SEPARACION), $y, $ancho, 9200 + $i);
		$tmp = new DOMDocument();
		$tmp->loadXML($xml);
		$forma->parentNode->insertBefore($dom->importNode($tmp->documentElement, true), $forma);
	}
	$forma->parentNode->removeChild($forma);
}

// graphicFrame con la tabla: franja de título (celda combinada), cabecera y filas.
function ep_ppt_tabla_xml(string $titulo, array $columnas, array $filas, int $x, int $y, int $ancho, int $id): string {
	$anchos = array_map(fn($c) => (int) floor($ancho * $c[1]), $columnas);
	$grid = implode('', array_map(fn($w) => '<a:gridCol w="'.$w.'"/>', $anchos));
	$n = count($columnas);
	$franja = '<a:tr h="'.(EP_PPT_TABLA_FILA * 2).'">'.ep_ppt_tabla_celda($titulo, 'ctr', EP_PPT_TABLA_FUENTE + 7, true, 'FFFFFF', EP_PPT_TABLA_AZUL, ' gridSpan="'.$n.'"');
	for ($i = 1; $i < $n; $i++) {
		$franja .= '<a:tc hMerge="1"><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="es-EC" sz="'.(EP_PPT_TABLA_FUENTE * 100).'"/></a:p></a:txBody><a:tcPr/></a:tc>';
	}
	$xml = $franja.'</a:tr><a:tr h="'.EP_PPT_TABLA_FILA.'">';
	foreach ($columnas as $c) {
		// La primera columna (nombre del material) no lleva cabecera azul, como en el formato de Epson.
		$xml .= ep_ppt_tabla_celda($c[0], 'ctr', EP_PPT_TABLA_FUENTE, true, 'FFFFFF', $c[0] === '' ? null : EP_PPT_TABLA_AZUL);
	}
	$xml .= '</a:tr>';
	foreach ($filas as $f) {
		$xml .= '<a:tr h="'.EP_PPT_TABLA_FILA.'">';
		foreach ($f['celdas'] as $i => $texto) {
			$xml .= ep_ppt_tabla_celda((string) $texto, $columnas[$i][2], EP_PPT_TABLA_FUENTE, !empty($f['negrita']), '1F2433', null);
		}
		$xml .= '</a:tr>';
	}
	$alto = EP_PPT_TABLA_FILA * (count($filas) + 3);
	return '<p:graphicFrame xmlns:p="'.EP_PPT_NS_P.'" xmlns:a="'.EP_PPT_NS_A.'">'
		.'<p:nvGraphicFramePr><p:cNvPr id="'.$id.'" name="Tabla '.($id - 9199).'"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>'
		.'<p:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$ancho.'" cy="'.$alto.'"/></p:xfrm>'
		.'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr/>'
		.'<a:tblGrid>'.$grid.'</a:tblGrid>'.$xml.'</a:tbl></a:graphicData></a:graphic></p:graphicFrame>';
}

// Una celda sin bordes; $fondo null = sin relleno.
function ep_ppt_tabla_celda(string $texto, string $alineacion, int $fuente, bool $negrita, string $color, ?string $fondo, string $extra = ''): string {
	$sinBorde = '<a:lnL w="0"><a:noFill/></a:lnL><a:lnR w="0"><a:noFill/></a:lnR><a:lnT w="0"><a:noFill/></a:lnT><a:lnB w="0"><a:noFill/></a:lnB>';
	$relleno = $fondo ? '<a:solidFill><a:srgbClr val="'.$fondo.'"/></a:solidFill>' : '<a:noFill/>';
	return '<a:tc'.$extra.'><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:pPr algn="'.$alineacion.'"/>'
		.'<a:r><a:rPr lang="es-EC" sz="'.($fuente * 100).'"'.($negrita ? ' b="1"' : '').' dirty="0"><a:solidFill><a:srgbClr val="'.$color.'"/></a:solidFill></a:rPr>'
		.'<a:t>'.htmlspecialchars($texto, ENT_XML1).'</a:t></a:r><a:endParaRPr lang="es-EC" sz="'.($fuente * 100).'" dirty="0"/></a:p></a:txBody>'
		.'<a:tcPr marL="45720" marR="45720" marT="0" marB="0" anchor="ctr">'.$sinBorde.$relleno.'</a:tcPr></a:tc>';
}
