<?php
// Arma el HTML del Acta (compatible con Dompdf: tablas, no flexbox/grid).
// Separado de getters/generar_acta_pdf.php para poder probarlo sin sesión ni base real.

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function moneda($v) { return '$' . number_format((float) $v, 2); }
// Distribuidor mide en cajas, no dólares (ver $fmt en generar_acta_html) —
// mismo formato que moneda() sin el signo "$".
function numero($v) { return number_format((float) $v, 2); }

function valores_por_mes(array $linea, array $mesesActivos) {
	return array_map(function ($m) use ($linea) {
		return (float) ($linea['valores_mensuales'][(string) $m] ?? 0);
	}, $mesesActivos);
}

// Dompdf ignora <colgroup>/<col> con table-layout:fixed — el ancho de columna
// solo se lee del `width` en % puesto en cada <th> (px no funciona).
function ancho_style($pct) { return 'width:'.round($pct, 2).'%'; }

// Tablas de 2.a/2.b: solo Marca (sin Segmento/Categoría, igual que el preview del navegador).
// $fmt: 'moneda' (Directo) o 'numero' (Distribuidor, sin signo "$" — ver generar_acta_html).
function tabla_marca_html($lineas, array $mesesActivos, array $mesesCorto, $valorFn, $anchoMarcaPct, $anchoMesesPct, $anchoTotalPct, $fmt = 'moneda', $fuenteBasePx = 20, $medirTexto = null) {
	$filas = [];
	foreach ($lineas as $linea) {
		if ($linea['marca'] === '' || $linea['marca'] === null) continue;
		$valores = $valorFn($linea, $mesesActivos);
		$filas[] = ['marca' => $linea['marca'], 'valores' => $valores, 'total' => array_sum($valores)];
	}
	$anchoMesPct = count($mesesActivos) > 0 ? $anchoMesesPct / count($mesesActivos) : 0;
	// Mide el valor más ancho de cada columna (meses/total) entre todas las
	// filas reales, para que ningún número se corte a la mitad al envolver.
	$fuenteMeses = $fuenteBasePx; $fuenteTotal = $fuenteBasePx;
	if ($medirTexto !== null && $filas) {
		$mesesTextos = []; $totalTextos = [];
		foreach ($filas as $f) {
			foreach ($f['valores'] as $v) $mesesTextos[] = $fmt($v);
			$totalTextos[] = $fmt($f['total']);
		}
		$fuenteMeses = fuente_columna_valores($mesesTextos, $fuenteBasePx, $anchoMesPct, $medirTexto);
		$fuenteTotal = fuente_columna_valores($totalTextos, $fuenteBasePx, $anchoTotalPct, $medirTexto);
	}
	$rows = '';
	foreach ($filas as $f) {
		$rows .= '<tr><td>'.h($f['marca']).'</td>';
		foreach ($f['valores'] as $v) $rows .= '<td class="num" style="font-size:'.round($fuenteMeses, 2).'px;">'.$fmt($v).'</td>';
		$rows .= '<td class="num" style="font-size:'.round($fuenteTotal, 2).'px;">'.$fmt($f['total']).'</td></tr>';
	}
	if ($rows === '') {
		$colspanVacio = 1 + count($mesesActivos) + 1;
		$rows = '<tr><td colspan="'.$colspanVacio.'" class="vacio">Sin datos</td></tr>';
	}
	$mesesHead = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesPct) {
		return '<th class="num" style="'.ancho_style($anchoMesPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));
	$marcaHead = '<th style="'.ancho_style($anchoMarcaPct).'">Marca</th>';
	// Distribuidor dice "Pago Total Cajas", Directo se queda con "Pago Total"
	// ($fmt distingue el canal).
	$totalHead = '<th style="'.ancho_style($anchoTotalPct).'">'.($fmt === 'numero' ? 'Pago Total Cajas' : 'Pago Total').'</th>';
	return [$rows, $marcaHead, $mesesHead, $totalHead];
}

function px($n, $escala) { return round($n * $escala, 2) . 'px'; }

// Igual criterio que ancho_columna_categoria()/fuente_una_linea(), pero para
// una columna de VALORES numéricos: mide el más ancho entre todos los que van
// a aparecer ahí (cada fila + el total, si lo hay) para que ningún número
// quede cortado a la mitad al envolver — antes solo la columna Categoría
// tenía esta protección, los números confiaban en que el % fijo de columna
// alcanzara siempre, y con sumas grandes (Total Período, Estimado a Ganar)
// no alcanzaba.
function fuente_columna_valores(array $textos, $fuenteBasePx, $anchoColPct, $medirTexto, $paddingPx = 10) {
	$anchoMax = 0;
	foreach ($textos as $t) $anchoMax = max($anchoMax, $medirTexto($t, $fuenteBasePx));
	if ($anchoMax === 0) return $fuenteBasePx;
	$anchoDisponible = (ACTA_ANCHO_UTIL_PX * $anchoColPct / 100 - $paddingPx) / 1.25;
	if ($anchoMax <= $anchoDisponible) return $fuenteBasePx;
	return $fuenteBasePx * ($anchoDisponible / $anchoMax);
}

// Data URI evita depender de cómo Dompdf resuelve rutas en el servidor.
// Sin la extensión GD de PHP se cae todo el PDF, así que se omite el logo si no está disponible.
function logo_base64() {
	static $cache = null;
	if ($cache === null) {
		$cache = '';
		if (extension_loaded('gd')) {
			$ruta = __DIR__.'/../assets/img/logo_alicorp.png';
			if (is_file($ruta)) $cache = 'data:image/png;base64,'.base64_encode(file_get_contents($ruta));
		}
	}
	return $cache;
}

if (!defined('ACTA_ANCHO_UTIL_PX')) define('ACTA_ANCHO_UTIL_PX', (210 - 24) * 96 / 25.4);

// Mide el ancho real del texto con el motor de fuentes de Dompdf, no un ratio
// de caracter inventado.
function crear_medidor_texto() {
	$options = new \Dompdf\Options();
	$options->set('isRemoteEnabled', false);
	$dompdf = new \Dompdf\Dompdf($options);
	$fontMetrics = $dompdf->getFontMetrics();
	$font = $fontMetrics->getFont('DejaVu Sans', 'normal');
	return function ($texto, $tamanoFuente) use ($fontMetrics, $font) {
		if ($texto === '') return 0;
		$ancho = $font ? $fontMetrics->getTextWidth($texto, $font, $tamanoFuente) : 0;
		// Si el medidor real falla (fuentes incompletas en el servidor) no
		// confiar en un 0 falso; usa el estimado por caracter como red de seguridad.
		if ($ancho <= 0) $ancho = mb_strlen($texto) * $tamanoFuente * 0.66;
		return $ancho;
	};
}

// *1.25 de margen de seguridad amplio para no arriesgar que el texto se recorte de nuevo.
function fuente_una_linea($texto, $fuenteBasePx, $anchoColPct, $medirTexto, $paddingPx = 10) {
	$anchoDisponible = (ACTA_ANCHO_UTIL_PX * $anchoColPct / 100 - $paddingPx) / 1.25;
	$anchoTexto = $medirTexto($texto, $fuenteBasePx);
	if ($anchoTexto <= $anchoDisponible) return $fuenteBasePx;
	// Sin piso "legible": la regla es una sola línea SIEMPRE, sin excepción.
	return $fuenteBasePx * ($anchoDisponible / $anchoTexto);
}

// Ensancha la columna Categoría según el nombre más largo, restando ese % a
// meses/totales. anchoMinPct/anchoMaxPct limitan cuánto puede crecer.
function ancho_columna_categoria(array $textos, $fuenteBasePx, $medirTexto, $anchoMinPct = 22, $anchoMaxPct = 48, $paddingPx = 10) {
	$anchoMaxTextoPx = 0;
	foreach ($textos as $t) $anchoMaxTextoPx = max($anchoMaxTextoPx, $medirTexto($t, $fuenteBasePx));
	if ($anchoMaxTextoPx === 0) return $anchoMinPct;
	$anchoNecesarioPx = $anchoMaxTextoPx * 1.25 + $paddingPx;
	$pct = ($anchoNecesarioPx / ACTA_ANCHO_UTIL_PX) * 100;
	return max($anchoMinPct, min($anchoMaxPct, $pct));
}

// Sector/% Participación no se guardan en repositorio_acuerdo_lineas, por eso este PDF no las muestra.
// $escala reduce texto general (título/condiciones/firmas); $escalaTabla reduce solo las celdas de tabla, independiente entre sí.
function generar_acta_html(array $detalle, $escala = 1.0, $medirTexto = null, $escalaTabla = 1.0) {
	if ($medirTexto === null) $medirTexto = crear_medidor_texto();

	// Formato Distribuidor: título/firma distintos, C.I. en la firma del cliente, mide en Cajas (ver $fmt),
	// y "Estimado a Ganar" = Total x Rebate% (Directo usa Total x (1+Rebate%)).
	$esDistribuidor = !empty($detalle['es_distribuidor']);

	// "Sin visibilidad" es independiente del canal (switch de Registrar) —
	// oculta 2.a/2.b para Directo y Distribuidor por igual.
	$sinVisibilidad = !empty($detalle['sin_visibilidad']);
	$ocultarVisibilidad = $sinVisibilidad;

	// "Con visibilidad" (Directo y Distribuidor) ya está aprobado — no tocar
	// estos valores. Con menos contenido (sin las tablas 2.a/2.b), "sin
	// visibilidad" deja el documento con demasiado espacio en blanco y
	// letras desproporcionadas — pedido explícito: SOLO ahí la letra
	// general baja un poco más y la de las tablas sube un poco.
	$fGeneral = $sinVisibilidad ? 13.5 : 24;
	$fH1 = $sinVisibilidad ? 19 : 31;
	$fDocNo = $sinVisibilidad ? 10.5 : 17;
	$fDocNoStrong = $sinVisibilidad ? 14.5 : 25;
	$fHintExtra = $sinVisibilidad ? 12.5 : 27;
	// Tablas: base normal (18.5/16.5) en "con visibilidad" (ya aprobado, sin
	// tocar); más grande SOLO en "sin visibilidad".
	$tablaFuenteBase = $sinVisibilidad ? 22 : 18.5;
	$legendFuenteBase = $sinVisibilidad ? 20 : 16.5;

	// Distribuidor mide en cajas, no dólares (ver numero()).
	$fmt = $esDistribuidor ? 'numero' : 'moneda';

	$logo = logo_base64();
	$logoHtml = $logo ? '<div style="text-align:center; margin-bottom:'.px(5, $escala).';"><img src="'.$logo.'" style="height:'.px(120, $escala).'; width:auto;"></div>' : '';

	$mesesCorto = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
	$mesesLargo = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

	$mesesActivos  = range($detalle['mes_inicio'], $detalle['mes_fin']);
	$cantidadMeses = count($mesesActivos);

	// 1ra pasada: texto de cada categoría, para saber cuánto tiene que crecer
	// esa columna antes de armar las filas (ver ancho_columna_categoria()).
	$categoriaTextos = array_map(function ($linea) {
		return trim($linea['segmento'].' '.$linea['categoria'].' '.$linea['marca']);
	}, $detalle['lineas']['meta_compra']);
	// Tope de Categoría en 38% (no 48%) para dejar suficiente ancho al
	// encabezado "REBATE" en 1 línea.
	$categoriaPct = round(ancho_columna_categoria($categoriaTextos, $tablaFuenteBase * $escalaTabla, $medirTexto, 22, 38), 2);
	$restoPct = 100 - $categoriaPct;
	// Rebate pesa 16 (doble de Total Período/Estimado a Ganar, 12 cada uno)
	// para que "REBATE" entre en 1 línea; denominador 74 sin cambios.
	$mesesPct    = round(34 * $restoPct / 74, 2);
	$totalPct    = round(12 * $restoPct / 74, 2);
	$rebatePct   = round(16 * $restoPct / 74, 2);
	$estimadoPct = round(12 * $restoPct / 74, 2);

	$anchoMesMetaPct = $cantidadMeses > 0 ? $mesesPct / $cantidadMeses : 0;
	$metaFilas = []; $metaSums = array_fill(0, $cantidadMeses, 0.0); $metaGrandTotal = 0.0; $metaGrandEst = 0.0;
	foreach ($detalle['lineas']['meta_compra'] as $i => $linea) {
		$valores = valores_por_mes($linea, $mesesActivos);
		foreach ($valores as $j => $v) $metaSums[$j] += $v;
		$total  = array_sum($valores);
		$rebate = (float) $linea['rebate_pct'];
		// Distribuidor: Total x Rebate% (solo el bono). Directo: Total x
		// (1+Rebate%) (valor total del trato).
		$est    = $esDistribuidor ? ($total * $rebate) : ($total * (1 + $rebate));
		$metaGrandTotal += $total; $metaGrandEst += $est;
		$metaFilas[] = ['categoria' => $categoriaTextos[$i], 'valores' => $valores, 'total' => $total, 'rebate' => $rebate, 'est' => $est];
	}

	// Los números también necesitan medirse: si el valor más ancho de la
	// columna (incluida la fila Total) no entra a esta fuente, se corta a la
	// mitad al envolver en vez de quedarse en 1 línea — mismo criterio que
	// Categoría, ahora aplicado a meses/Total Período/Estimado a Ganar.
	$mesesTextos = []; $totalTextos = [$fmt($metaGrandTotal)]; $estTextos = [$fmt($metaGrandEst)];
	foreach ($metaFilas as $fila) {
		foreach ($fila['valores'] as $v) $mesesTextos[] = $fmt($v);
		$totalTextos[] = $fmt($fila['total']);
		$estTextos[] = $fmt($fila['est']);
	}
	foreach ($metaSums as $s) $mesesTextos[] = $fmt($s);
	$fuenteMesesMeta = fuente_columna_valores($mesesTextos, $tablaFuenteBase * $escalaTabla, $anchoMesMetaPct, $medirTexto);
	$fuenteTotalMeta = fuente_columna_valores($totalTextos, $tablaFuenteBase * $escalaTabla, $totalPct, $medirTexto);
	$fuenteEstMeta   = fuente_columna_valores($estTextos, $tablaFuenteBase * $escalaTabla, $estimadoPct, $medirTexto);

	$metaRows = '';
	foreach ($metaFilas as $fila) {
		$fuenteCategoria = fuente_una_linea($fila['categoria'], $tablaFuenteBase * $escalaTabla, $categoriaPct, $medirTexto);
		// Una sola línea horizontal SIEMPRE (requisito explícito, sin excepción):
		// nowrap fuerza 1 línea y el tamaño ya viene calculado para que quepa.
		$metaRows .= '<tr><td style="white-space:nowrap; overflow:hidden; font-size:'.round($fuenteCategoria, 2).'px;">'.h($fila['categoria']).'</td>';
		foreach ($fila['valores'] as $v) $metaRows .= '<td class="num" style="font-size:'.round($fuenteMesesMeta, 2).'px;">'.$fmt($v).'</td>';
		$metaRows .= '<td class="num" style="font-size:'.round($fuenteTotalMeta, 2).'px;">'.$fmt($fila['total']).'</td>';
		$metaRows .= '<td class="ctr rebate-cell">'.number_format($fila['rebate'] * 100, 1).'%</td>';
		$metaRows .= '<td class="num" style="font-size:'.round($fuenteEstMeta, 2).'px;">'.$fmt($fila['est']).'</td></tr>';
	}
	if ($metaRows === '') {
		$colspanMeta = 1 + $cantidadMeses + 3;
		$metaRows = '<tr><td colspan="'.$colspanMeta.'" class="vacio">Sin datos</td></tr>';
	}
	$mesesHeadHtml = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesMetaPct) {
		return '<th class="num" style="'.ancho_style($anchoMesMetaPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));

	$cabecerasValorFn = function ($linea, $mesesActivos) { return valores_por_mes($linea, $mesesActivos); };
	list($cabecerasRows, $marcaHeadCab, $mesesHeadCab, $totalHeadCab) = tabla_marca_html($detalle['lineas']['cabecera'], $mesesActivos, $mesesCorto, $cabecerasValorFn, 20, 62, 18, $fmt, $tablaFuenteBase * $escalaTabla, $medirTexto);

	$rumaValorFn = function ($linea, $mesesActivos) { return array_fill(0, count($mesesActivos), (float) $linea['valor_mensual_unico']); };
	list($rumasRows, $marcaHeadRuma, $mesesHeadRuma, $totalHeadRuma) = tabla_marca_html($detalle['lineas']['ruma'], $mesesActivos, $mesesCorto, $rumaValorFn, 20, 62, 18, $fmt, $tablaFuenteBase * $escalaTabla, $medirTexto);

	$rumaLegendRows = '';
	$marcasVistas = [];
	foreach ($detalle['lineas']['ruma'] as $linea) {
		if ($linea['marca'] === '' || isset($marcasVistas[$linea['marca']])) continue;
		$marcasVistas[$linea['marca']] = true;
		$rumaLegendRows .= '<tr><td>'.h($linea['marca']).'</td><td class="num">'.$fmt($linea['valor_mensual_unico']).'</td></tr>';
	}
	if ($rumaLegendRows === '') $rumaLegendRows = '<tr><td colspan="2" class="vacio">Sin datos</td></tr>';

	// Sin subtítulo propio a propósito: va bajo el título combinado "2.b. Espacio en Perchas & Rumas".
	$anchoMesPerchaPct = $cantidadMeses > 0 ? 38 / $cantidadMeses : 0;
	$perchaRows = ''; $mesesHeadPercha = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesPerchaPct) {
		return '<th class="num" style="'.ancho_style($anchoMesPerchaPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));
	// Mismo encabezado de 3 filas (rowspan/colspan) que la tabla de Perchas del
	// formulario interactivo, sin la columna "eliminar fila".
	$perchaHeadRow1 = '<tr>'
		.'<th rowspan="3" style="'.ancho_style(18).'">Marca Perchas</th>'
		.'<th style="'.ancho_style(14).'">Participación</th>'
		.'<th style="'.ancho_style(10).'">Cantidad</th>'
		.'<th colspan="'.($cantidadMeses + 1).'">Pago Mensual</th>'
		.'</tr>';
	$perchaHeadRow2 = '<tr><th colspan="'.($cantidadMeses + 2).'">Pago x Mes x Percha ($)</th></tr>';
	// "Pago Total Cajas" (2026-08-25, pedido explícito, solo Distribuidor,
	// mismo criterio que tabla_marca_html() más arriba).
	$perchaHeadRow3 = '<tr>'
		.'<th style="'.ancho_style(14).'">% de Peso</th>'
		.'<th style="'.ancho_style(10).'">Max Percha</th>'
		.$mesesHeadPercha
		.'<th style="'.ancho_style(20).'">'.($esDistribuidor ? 'Pago Total Cajas' : 'Pago Total').'</th>'
		.'</tr>';
	$perchaFilas = [];
	foreach ($detalle['lineas']['percha'] as $linea) {
		if ($linea['marca'] === '') continue;
		$valores = valores_por_mes($linea, $mesesActivos);
		$perchaFilas[] = ['marca' => $linea['marca'], 'participacion' => $linea['participacion'], 'cantidad_max_percha' => $linea['cantidad_max_percha'], 'valores' => $valores, 'total' => array_sum($valores)];
	}
	// Mismo criterio de medición que Meta de Compras/Cabeceras/Rumas: el valor
	// más ancho de meses/Pago Total decide la fuente de toda la columna.
	$fuenteMesesPercha = $tablaFuenteBase * $escalaTabla; $fuenteTotalPercha = $tablaFuenteBase * $escalaTabla;
	if ($perchaFilas) {
		$mesesTextosPercha = []; $totalTextosPercha = [];
		foreach ($perchaFilas as $f) {
			foreach ($f['valores'] as $v) $mesesTextosPercha[] = $fmt($v);
			$totalTextosPercha[] = $fmt($f['total']);
		}
		$fuenteMesesPercha = fuente_columna_valores($mesesTextosPercha, $tablaFuenteBase * $escalaTabla, $anchoMesPerchaPct, $medirTexto);
		$fuenteTotalPercha = fuente_columna_valores($totalTextosPercha, $tablaFuenteBase * $escalaTabla, 20, $medirTexto);
	}
	foreach ($perchaFilas as $f) {
		$perchaRows .= '<tr><td>'.h($f['marca']).'</td><td class="ctr">'.h($f['participacion'] !== '' ? $f['participacion'] : '—').'</td><td class="ctr">'.(int) $f['cantidad_max_percha'].'</td>';
		foreach ($f['valores'] as $v) $perchaRows .= '<td class="num" style="font-size:'.round($fuenteMesesPercha, 2).'px;">'.$fmt($v).'</td>';
		$perchaRows .= '<td class="num" style="font-size:'.round($fuenteTotalPercha, 2).'px;">'.$fmt($f['total']).'</td></tr>';
	}
	if ($perchaRows === '') {
		$colspanPercha = 3 + $cantidadMeses + 1;
		$perchaRows = '<tr><td colspan="'.$colspanPercha.'" class="vacio">Sin datos</td></tr>';
	}

	$periodoTexto = implode(' ', array_map(function ($m) use ($mesesLargo) { return $mesesLargo[$m]; }, $mesesActivos));
	$fechaTexto   = $detalle['fecha_generacion'] ? date('d/m/Y', strtotime($detalle['fecha_generacion'])) : '—';

	// Nombres de cliente largos (ej. "ACONDA SIMBANA MARGARITA DE LOURDES")
	// envuelven a 2 líneas dentro del 34% de la columna — se ven "chicos"
	// frente a Localidad/Fecha (misma letra, solo que partida en 2 líneas se
	// percibe más densa/chica en una miniatura). Forzar 1 sola línea aquí
	// (como en Categoría) NO es viable: incluso a un ancho de columna mucho
	// mayor, un nombre así de largo seguiría sin entrar, y forzarlo igual
	// dejaría una letra microscópica — peor que el problema original. En vez
	// de eso, se le da más ancho a esta columna (34%→44%, a costa de
	// Localidad/Fecha) para que la mayoría de los nombres reales entren en 1
	// línea sin necesidad de achicar nada; los pocos casos extremos que
	// igual envuelvan a 2 líneas lo hacen al MISMO tamaño que Localidad/Fecha
	// (nunca más chico).
	$estimadoTexto = $esDistribuidor && ($detalle['empresa_distribuidora'] ?? '') !== '' ? $detalle['empresa_distribuidora'] : $detalle['distribuidor'];

	// Nombre del Ejecutivo Comercial = quien generó el acuerdo (creado_por); la firma sigue siendo física siempre.
	// Sin creado_por (acuerdo huérfano) cae a la línea en blanco de siempre.
	$nombreEjecutivoHtml = ($detalle['ejecutivo_comercial'] ?? '') !== ''
		? 'Nombre: '.h($detalle['ejecutivo_comercial'])
		: 'Nombre: ________________________________________';

	// Dompdf usa el <title> del HTML como metadato /Title del PDF; sin esto la
	// pestaña del navegador mostraba el nombre del script, no el documento.
	$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.h($detalle['documento_no']).'</title><style>
@page { size: A4; margin: 1cm 1.2cm; }
* { box-sizing: border-box; }
p, h1, ul { margin: 0 0 '.px(3.5, $escala).'; }
body { font-family: "DejaVu Sans", sans-serif; font-size: '.px($fGeneral, $escala).'; color: #000000; line-height: 1.35; }
h1 { font-size: '.px($fH1, $escala).'; text-align: center; text-transform: uppercase; margin: '.px(3, $escala).' 0 '.px(5.5, $escala).'; padding-right: '.px(150, $escala).'; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
/* Las celdas de tabla fijan su propio tamaño (no heredan de body) para que subir el texto general no arrastre los datos de tabla. */
/* th/td/legend-box/márgenes de tabla usan $escalaTabla, nunca $escala, para poder achicar solo las tablas primero. */
th { padding: '.px(7, $escalaTabla).' '.px(11, $escalaTabla).'; word-wrap: break-word; font-size: '.px($tablaFuenteBase, $escalaTabla).'; }
/* Filas de datos con menos padding vertical que el encabezado; antes compartían el mismo padding y quedaban más altas de lo necesario. */
td { padding: '.px(4, $escalaTabla).' '.px(11, $escalaTabla).'; word-wrap: break-word; font-size: '.px($tablaFuenteBase, $escalaTabla).'; }
.num { text-align: right; }
.ctr { text-align: center; }
.vacio { text-align: center; color: #000000; padding: '.px(7, $escalaTabla).' !important; }
.doc-no { position: fixed; top: '.px(14, $escala).'; right: '.px(14, $escala).'; text-align: right; font-size: '.px($fDocNo, $escala).'; color: #000000; }
.doc-no strong { display: block; font-size: '.px($fDocNoStrong, $escala).'; }
.meta-tabla { margin: '.px(6, $escalaTabla).' 0 '.px(5, $escalaTabla).'; }
/* Margen extra solo bajo la tabla de Meta de Compras, no Cabeceras/Rumas/Perchas. */
.meta-tabla-compras { margin-bottom: '.px(14, $escalaTabla).'; }
.meta-tabla td, .meta-tabla th { border: 1px solid #c4c5d5; }
.meta-tabla thead th { background: #eeedf7; }
.total-row td { font-weight: bold; border-top: 2px solid #000000; }
.rebate-cell { background: #fbf0cf; }
.label { font-size: '.px($fGeneral, $escala).'; text-transform: uppercase; letter-spacing: 0.05em; color: #000000; }
/* Mismo motivo que .subtitulo: si un hint queda justo debajo de un subtítulo
   al inicio de página, su 1ra línea todavía puede caer dentro del recuadro
   fijo "Documento No". */
.hint { font-size: '.px($fHintExtra, $escala).'; color: #000000; margin: 0 0 '.px(2.5, $escala).'; padding-right: '.px(130, $escala).'; }
/* padding-right reserva el espacio del recuadro fijo "Documento No" (igual
   que ya hacía h1) — sin esto, un subtítulo que cae justo al inicio de una
   página nueva (2.a/2.b/Consideraciones Generales, con varias tablas) queda
   pisado por ese recuadro, que se repite en todas las páginas. */
.subtitulo { font-size: '.px($fGeneral, $escala).'; text-transform: uppercase; margin: '.px(6.5, $escala).' 0 '.px(2.5, $escala).'; padding-right: '.px(130, $escala).'; font-weight: bold; color: #000000; }
.condiciones { background: #f4f2fc; border: 1px solid #c4c5d5; border-radius: 6px; padding: '.px(6, $escala).' '.px(10, $escala).'; margin: '.px(3.5, $escala).' 0 '.px(5, $escala).'; }
.condiciones h3 { font-size: '.px($fGeneral, $escala).'; text-transform: uppercase; margin: 0 0 '.px(2.5, $escala).'; color: #000000; }
.condiciones ul { margin: 0; padding-left: '.px(17, $escala).'; }
.condiciones li { margin-bottom: '.px(1.5, $escala).'; font-size: '.px($fHintExtra, $escala).'; }
.firmas-footer { margin-top: '.px(18, $escala).'; }
.firma-linea-firmar { border-bottom: 1px solid #000000; height: '.px(28, $escala).'; margin: '.px(32, $escala).' 0; }
.legend-box { border: 1px solid #c4c5d5; border-radius: 4px; padding: '.px(5, $escalaTabla).'; }
.legend-box th, .legend-box td { font-size: '.px($legendFuenteBase, $escalaTabla).'; }
</style></head><body>

<div class="doc-no"><span class="label">Documento No:</span><strong>'.h($detalle['documento_no']).'</strong></div>
'.$logoHtml.'
<h1>'.($esDistribuidor ? 'Acuerdo Comercial Canal Distribuidores' : 'Acuerdo de Desarrollo de Negocios Canal Directo').'</h1>

<table style="border-top:1px solid #757684; border-bottom:1px solid #757684; margin-bottom:'.px(5, $escala).';"><tr>
	<td style="border:none; width:44%;"><span class="label">Estimado(a)</span><br><strong>'.h($estimadoTexto).'</strong></td>
	<td style="border:none; width:28%;"><span class="label">Localidad</span><br><strong>'.h($detalle['localidad']).'</strong></td>
	<td style="border:none; width:28%;"><span class="label">Fecha</span><br><strong>'.h($fechaTexto).'</strong></td>
</tr></table>

<p>JABONERÍA WILSON S.A. y '.h($detalle['distribuidor']).' celebran el presente acuerdo de desarrollo de negocios para el fortalecimiento mutuo en el mercado regional.</p>
<p><span class="label">Periodo del acuerdo</span> <strong>'.h($periodoTexto).'</strong></p>

<p class="subtitulo">'.($esDistribuidor ? '1. Meta de Compras en Cajas' : '1. Meta de Compras en Dólares + Home Care').'</p>
<p class="hint">'.($esDistribuidor ? 'Cajas compradas por categoría sin considerar cajas a título gratuito por bonificación/descuentos.' : 'Dólares comprados por categoría sin considerar bonificación/descuentos.').'</p>
<table class="meta-tabla meta-tabla-compras">
	<thead>
	<!-- Fila combinada "Meta en Dólares"/"Meta en Cajas": rowspan/colspan sobre mes+Total Período; el ancho va en la 2da fila (celda por columna). -->
	<tr>
		<th rowspan="2" style="'.ancho_style($categoriaPct).'">Categoría</th>
		<th colspan="'.($cantidadMeses + 1).'">'.($esDistribuidor ? 'Meta en Cajas' : 'Meta en Dólares').'</th>
		<th rowspan="2" style="'.ancho_style($rebatePct).'">Rebate</th>
		<th rowspan="2" style="'.ancho_style($estimadoPct).'">'.($esDistribuidor ? 'Valor Estimado a Ganar' : 'Estimado a Ganar').'</th>
	</tr>
	<tr>'.$mesesHeadHtml.'<th style="'.ancho_style($totalPct).'">Total Período</th></tr>
	</thead>
	<tbody>'.$metaRows.'</tbody>
	<tfoot><tr class="total-row"><td>Total</td>';
	foreach ($metaSums as $s) $html .= '<td class="num" style="font-size:'.round($fuenteMesesMeta, 2).'px;">'.$fmt($s).'</td>';
	$html .= '<td class="num" style="font-size:'.round($fuenteTotalMeta, 2).'px;">'.$fmt($metaGrandTotal).'</td><td class="ctr">—</td><td class="num" style="font-size:'.round($fuenteEstMeta, 2).'px;">'.$fmt($metaGrandEst).'</td></tr></tfoot>
</table>

<div class="condiciones">
	<h3>Condiciones</h3>
	<ul>
		<li><strong>a)</strong> Cumplir con la meta del período en '.($esDistribuidor ? 'cajas netas' : 'dólares netos').' al 100%.</li>
		<li><strong>b)</strong> Para liquidación del rebate se debe considerar:
			<ul>
				<li>Cumplir con el 100% de la cuota total del período.</li>
				<li>Compra mínima del 80% de la meta asignada en todas las categorías. No se reconocerá el pago del rebate de la categoría con cumplimientos por debajo del 80%.</li>
			</ul>
		</li>
		<li><strong>c)</strong> Al final de cada mes no se deben mantener saldos vencidos de cartera.</li>
		<li><strong>d)</strong> Solo se cancelará hasta el 110% de cumplimiento total y por categoría.</li>
	</ul>
</div>

'.($ocultarVisibilidad ? '' : '
<p class="subtitulo">2. Visibilidad</p>
<p class="subtitulo">2.a. Extravisibilidad: Cabeceras</p>
<p class="hint">Son prestaciones del cliente y por el cual se define un valor fijo a cancelar según el cuadro.<br>Se cancelará el valor acordado si, durante todo el período del acuerdo, se mantiene el o los espacios acordados.<br>En el caso de desabastecimientos y se incumple con el espacio acordado durante el lapso mínimo de 7 días, la bonificación total del mes no será cancelada.</p>
<table class="meta-tabla">
	<thead><tr>'.$marcaHeadCab.$mesesHeadCab.$totalHeadCab.'</tr></thead>
	<tbody>'.$cabecerasRows.'</tbody>
</table>

<p class="subtitulo">2.b. Espacio en Perchas &amp; Rumas</p>
<p class="hint">Se cancelará el valor acordado si, durante todo el período del acuerdo, las categorías mantienen el espacio acordado. La participación se considerará por número de caras/display.<br>En el caso de desabastecimientos y se incumple con el espacio acordado durante el lapso mínimo de 7 días, la bonificación total del mes no será cancelada.<br>El espacio debe estar demarcado con preciadores, polipasacalle, cenefas y cualquier otro elemento de visibilidad.</p>
<table style="border:none;"><tr>
	<td style="border:none; width:78%; vertical-align:top; padding:0;">
		<table class="meta-tabla" style="margin-top:0;">
			<thead><tr>'.$marcaHeadRuma.$mesesHeadRuma.$totalHeadRuma.'</tr></thead>
			<tbody>'.$rumasRows.'</tbody>
		</table>
	</td>
	<td style="border:none; width:2%;"></td>
	<td style="border:none; width:20%; vertical-align:top; padding:0;">
		<div class="legend-box">
			<span class="label">Valor Ruma x Marca x Mes</span>
			<table style="margin-top:'.px(4, $escalaTabla).';"><tbody>'.$rumaLegendRows.'</tbody></table>
		</div>
	</td>
</tr></table>

<table class="meta-tabla">
	<thead>'.$perchaHeadRow1.$perchaHeadRow2.$perchaHeadRow3.'</thead>
	<tbody>'.$perchaRows.'</tbody>
</table>
').'

<p class="subtitulo">Consideraciones Generales</p>
<p style="margin:'.px(3, $escala).' 0; font-size:'.px($fHintExtra, $escala).';">Al cierre de cada mes, usted nos facilitará la información de su inventario. <strong>OBLIGATORIO</strong>.</p>
<p style="margin:'.px(3, $escala).' 0; font-size:'.px($fHintExtra, $escala).';">'.($esDistribuidor
		? 'La liquidación del acuerdo se realizará al finalizar el periodo. El pago total será reconocido a través de producto. El plazo para entregar el producto es hasta 2 meses luego de finalizar el periodo del acuerdo.'
		: 'La liquidación del acuerdo se realizará al finalizar el periodo. El pago total será reconocido a través de nota de crédito. El plazo para emitir la nota de crédito es hasta 2 meses luego de finalizar el periodo del acuerdo.').'</p>
<p style="margin:'.px(3, $escala).' 0 '.px(26, $escala).'; font-size:'.px($fHintExtra, $escala).';">Como constancia del presente convenio, firman de común acuerdo las partes.</p>

<div class="firmas-footer">
'.($esDistribuidor && $sinVisibilidad ? '
<!-- Layout de 2 firmas exclusivo de Distribuidor+sin visibilidad: misma estructura que el resto, pero etiqueta derecha "Asesor Comercial (distribuidor)" en vez de "Jefe Comercial". -->
<table style="border:none;"><tr>
	<td style="border:none; width:50%; text-align:center; padding-right:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:0; font-weight:bold;">'.$nombreEjecutivoHtml.'</p>
		<p class="label" style="margin-top:'.px(8, $escala).';">Desarrollador de Mercado</p>
	</td>
	<td style="border:none; width:50%; text-align:center; padding-left:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:0; font-weight:bold;">Nombre: ________________________________________</p>
		<p class="label" style="margin-top:'.px(8, $escala).';">Asesor Comercial (distribuidor)</p>
	</td>
</tr></table>' : '
<table style="border:none;"><tr>
	<td style="border:none; width:50%; text-align:center; padding-right:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:0; font-weight:bold;">'.$nombreEjecutivoHtml.'</p>
		<p class="label" style="margin-top:'.px(8, $escala).';">'.($esDistribuidor ? 'Desarrollador de Mercado' : 'Ejecutivo Comercial').'</p>
	</td>
	<td style="border:none; width:50%; text-align:center; padding-left:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:0; font-weight:bold;">Nombre: ________________________________________</p>
		<p class="label" style="margin-top:'.px(8, $escala).';">Jefe Comercial</p>
	</td>
</tr></table>').'

<div style="text-align:center; margin-top:'.px(20, $escala).';">
	<p style="margin:0;">Jabonería Wilson<br><strong>ACEPTACIÓN DEL PRESENTE CONVENIO POR PARTE DEL CLIENTE</strong></p>
	<p style="font-size:'.px($fGeneral, $escala).'; color:#000000;">El CLIENTE declara expresamente que ha suscrito este Acuerdo a su entera satisfacción y entendimiento, de manera libre y voluntaria, por lo que nada tiene que reclamar sobre el contenido, la aplicación y/o ejecución del mismo.</p>
	<div class="firma-linea-firmar" style="width:'.px(220, $escala).'; margin:'.px(36, $escala).' auto;"></div>
	<p class="label" style="margin:0;">Firma del Cliente</p>'
	.($esDistribuidor ? '
	<p class="label" style="margin-top:'.px(8, $escala).';">Razón Social: <span style="display:inline-block; width:'.px(220, $escala).'; border-bottom:1px solid #000000;">&nbsp;</span></p>
	<p class="label" style="margin-top:'.px(4, $escala).'; padding-left:'.px(40, $escala).';">C.I.: <span style="display:inline-block; width:'.px(220, $escala).'; border-bottom:1px solid #000000;">&nbsp;</span></p>' : '
	<p class="label" style="margin-top:'.px(8, $escala).';">Razón Social: <span style="font-weight:bold; text-transform:none;">'.h($detalle['distribuidor']).'</span></p>').'
</div>

</div>

</body></html>';

	return $html;
}

// Renderiza el Acta completa a bytes de PDF (Dompdf), usada por guardar_acuerdo.php y generar_acta_pdf.php (fallback).
// El caller debe hacer require de vendor/autoload.php antes de llamar esto.
function generar_acta_pdf_binario(array $detalle) {
	$medirTexto = crear_medidor_texto();

	$renderizar = function ($escala, $escalaTabla) use ($detalle, $medirTexto) {
		$options = new \Dompdf\Options();
		$options->set('isRemoteEnabled', false);
		$dompdf = new \Dompdf\Dompdf($options);
		$dompdf->loadHtml(generar_acta_html($detalle, $escala, $medirTexto, $escalaTabla));
		$dompdf->setPaper('A4', 'portrait');
		$dompdf->render();
		return $dompdf;
	};

	// Primero se reduce SOLO $escalaTabla (letra y padding dentro de las
	// tablas), hasta 0.35 — nunca toca el texto general. Recién si con eso no
	// alcanza (probado con datos reales: sin este 2do paso, un Acta con las 4
	// tablas completas quedaba en 3 hojas en vez de 1), $escala baja como
	// último recurso, con un piso chico (0.3) — la diferencia real entre 1
	// hoja y 3 para ese caso.
	$escala = 1.0;
	$escalaTabla = 1.0;
	$dompdf = $renderizar($escala, $escalaTabla);
	while ($dompdf->getCanvas()->get_page_count() > 1 && $escalaTabla > 0.35) {
		$escalaTabla -= 0.05;
		$dompdf = $renderizar($escala, $escalaTabla);
	}
	while ($dompdf->getCanvas()->get_page_count() > 1 && $escala > 0.3) {
		$escala -= 0.05;
		$dompdf = $renderizar($escala, $escalaTabla);
	}

	return $dompdf->output();
}
?>
