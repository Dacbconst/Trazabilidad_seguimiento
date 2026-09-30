<?php
// Tabla del Calendario de Activaciones en el recuadro que antes ocupaba la foto.

const EP_PPT_CAL_COLUMNAS = [
	['FECHA', 0.11],
	['CIUDAD', 0.16],
	['PUNTO DE VENTA', 0.33],
	['PROMOTOR', 0.24],
	['ESTADO', 0.16],
];
const EP_PPT_CAL_FUENTE = 'Samsung Sharp Sans';
const EP_PPT_CAL_FILA_MIN = 135000; // alto mínimo de fila en EMU; por debajo el texto de 6 pt ya no entra
const EP_PPT_CAL_FILA_MAX = 380000;
const EP_PPT_CAL_MARGEN = 45720;
const EP_PPT_CAL_SEPARACION = 120000; // espacio entre las dos tablas cuando no alcanza una sola

// Reemplaza el recuadro $rect por la tabla; sin filas, solo quita el recuadro.
function ep_ppt_tabla_calendario(DOMDocument $dom, DOMXPath $xp, string $rect, array $filas): void {
	$forma = ep_ppt_forma($xp, $rect);
	$off = $forma ? $xp->query('.//a:xfrm/a:off', $forma)->item(0) : null;
	$ext = $forma ? $xp->query('.//a:xfrm/a:ext', $forma)->item(0) : null;
	if (!$off || !$ext || !$filas) {
		if ($forma) {
			$forma->parentNode->removeChild($forma);
		}
		return;
	}
	[$x, $y, $cx, $cy] = [(int) $off->getAttribute('x'), (int) $off->getAttribute('y'), (int) $ext->getAttribute('cx'), (int) $ext->getAttribute('cy')];
	usort($filas, fn($a, $b) => strcmp($a['fecha'].$a['ciudad'].$a['punto_venta'], $b['fecha'].$b['ciudad'].$b['punto_venta']));

	// Si no entra en una tabla van dos lado a lado; si tampoco, se corta con "Y N FILAS MÁS".
	$capacidad = intdiv($cy, EP_PPT_CAL_FILA_MIN) - 1;
	$bloques = count($filas) > $capacidad ? 2 : 1;
	$maximo = $capacidad * $bloques;
	if (count($filas) > $maximo) {
		$sobran = count($filas) - ($maximo - 1);
		$filas = array_slice($filas, 0, $maximo - 1);
		$filas[] = ['fecha' => '', 'ciudad' => '', 'punto_venta' => 'Y '.$sobran.' FILAS MÁS', 'promotor' => '', 'estado' => ''];
	}
	$porBloque = (int) ceil(count($filas) / $bloques);
	$altoFila = max(EP_PPT_CAL_FILA_MIN, min(EP_PPT_CAL_FILA_MAX, intdiv($cy, $porBloque + 1)));
	$fuente = max(6, min(11, (int) round($altoFila / 12700 * 0.42)));
	$anchoBloque = intdiv($cx - EP_PPT_CAL_SEPARACION * ($bloques - 1), $bloques);

	$padre = $forma->parentNode;
	foreach (array_chunk($filas, $porBloque) as $i => $trozo) {
		$xBloque = $x + $i * ($anchoBloque + EP_PPT_CAL_SEPARACION);
		$xml = ep_ppt_tabla_calendario_xml($trozo, $xBloque, $y, $anchoBloque, $altoFila, $fuente, 9100 + $i);
		$tmp = new DOMDocument();
		$tmp->loadXML($xml);
		$padre->insertBefore($dom->importNode($tmp->documentElement, true), $forma);
	}
	$padre->removeChild($forma);
}

// Tabla como graphicFrame; los textos se recortan para que ninguna fila crezca.
function ep_ppt_tabla_calendario_xml(array $filas, int $x, int $y, int $ancho, int $altoFila, int $fuente, int $id): string {
	$anchos = array_map(fn($c) => (int) floor($ancho * $c[1]), EP_PPT_CAL_COLUMNAS);
	$grid = implode('', array_map(fn($w) => '<a:gridCol w="'.$w.'"/>', $anchos));
	$filasXml = ep_ppt_cal_fila(array_column(EP_PPT_CAL_COLUMNAS, 0), $anchos, $altoFila, $fuente, true, 0);
	foreach ($filas as $n => $f) {
		$estado = $f['estado'] === 'cumplido' ? 'Ejecutada' : ($f['estado'] === 'pendiente' ? 'No ejecutada' : '');
		$fecha = $f['fecha'] !== '' ? date('d/m', strtotime($f['fecha'])) : '';
		$celdas = [$fecha, ep_ppt_mayus((string) $f['ciudad']), (string) $f['punto_venta'], ep_ppt_mayus((string) $f['promotor']), $estado];
		$filasXml .= ep_ppt_cal_fila($celdas, $anchos, $altoFila, $fuente, false, $n);
	}
	$alto = $altoFila * (count($filas) + 1);
	return '<p:graphicFrame xmlns:p="'.EP_PPT_NS_P.'" xmlns:a="'.EP_PPT_NS_A.'">'
		.'<p:nvGraphicFramePr><p:cNvPr id="'.$id.'" name="Tabla calendario '.($id - 9099).'"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>'
		.'<p:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$ancho.'" cy="'.$alto.'"/></p:xfrm>'
		.'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr firstRow="1" bandRow="1"/>'
		.'<a:tblGrid>'.$grid.'</a:tblGrid>'.$filasXml.'</a:tbl></a:graphicData></a:graphic></p:graphicFrame>';
}

// Fila de la tabla: cabecera azul Epson; cuerpo en bandas con una línea fina abajo.
function ep_ppt_cal_fila(array $celdas, array $anchos, int $altoFila, int $fuente, bool $cabecera, int $n): string {
	$fondo = $cabecera ? '10218B' : ($n % 2 === 0 ? 'FFFFFF' : 'F4F6FB');
	$xml = '<a:tr h="'.$altoFila.'">';
	foreach ($celdas as $c => $texto) {
		$color = $cabecera ? 'FFFFFF' : '1F2433';
		if (!$cabecera && $c === 4) {
			$color = $texto === 'Ejecutada' ? '137A3E' : 'C5221F';
		}
		$negrita = $cabecera || $c === 4 ? ' b="1"' : '';
		$texto = ep_ppt_cal_recortar((string) $texto, $anchos[$c], $fuente);
		$linea = $cabecera ? '<a:lnB w="0"><a:noFill/></a:lnB>' : '<a:lnB w="6350"><a:solidFill><a:srgbClr val="D8DEE9"/></a:solidFill></a:lnB>';
		$xml .= '<a:tc><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:pPr algn="l"/>'
			.'<a:r><a:rPr lang="es-EC" sz="'.($fuente * 100).'"'.$negrita.' dirty="0"><a:solidFill><a:srgbClr val="'.$color.'"/></a:solidFill><a:latin typeface="'.EP_PPT_CAL_FUENTE.'"/><a:cs typeface="'.EP_PPT_CAL_FUENTE.'"/></a:rPr>'
			.'<a:t>'.htmlspecialchars($texto, ENT_XML1).'</a:t></a:r><a:endParaRPr lang="es-EC" sz="'.($fuente * 100).'" dirty="0"/></a:p></a:txBody>'
			.'<a:tcPr marL="'.EP_PPT_CAL_MARGEN.'" marR="'.EP_PPT_CAL_MARGEN.'" marT="0" marB="0" anchor="ctr">'
			.'<a:lnL w="0"><a:noFill/></a:lnL><a:lnR w="0"><a:noFill/></a:lnR><a:lnT w="0"><a:noFill/></a:lnT>'.$linea
			.'<a:solidFill><a:srgbClr val="'.$fondo.'"/></a:solidFill></a:tcPr></a:tc>';
	}
	return $xml.'</a:tr>';
}

// Recorta con "..." (no "…": esta fuente no lo tiene y agranda la fila).
function ep_ppt_cal_recortar(string $texto, int $anchoCol, int $fuente): string {
	$disponiblePt = ($anchoCol - 2 * EP_PPT_CAL_MARGEN) / 12700;
	$maxLetras = max(4, (int) floor($disponiblePt / ($fuente * 0.55)));
	return mb_strlen($texto) > $maxLetras ? rtrim(mb_substr($texto, 0, $maxLetras - 3)).'...' : $texto;
}
