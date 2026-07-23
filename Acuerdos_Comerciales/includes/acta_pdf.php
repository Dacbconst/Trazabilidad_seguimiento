<?php
// Arma el HTML del Acta (compatible con Dompdf: tablas, no flexbox/grid) a
// partir de un $detalle con la forma de obtener_acuerdo_detalle(). Separado
// de getters/generar_acta_pdf.php para poder probarlo con datos de prueba
// sin sesión ni base de datos real.

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function moneda($v) { return '$' . number_format((float) $v, 2); }

function valores_por_mes(array $linea, array $mesesActivos) {
	return array_map(function ($m) use ($linea) {
		return (float) ($linea['valores_mensuales'][(string) $m] ?? 0);
	}, $mesesActivos);
}

// Dompdf IGNORA <colgroup>/<col> por completo en table-layout:fixed — se
// comprobó en el código fuente (Cellmap.php): el ancho de columna fijo solo
// se lee del estilo `width` puesto en las CELDAS (th/td) de la PRIMERA fila,
// nunca de <col>. Por eso el ancho va directo en cada <th> del encabezado.
// IMPORTANTE: probado en el PDF real — table-layout:fixed + ancho en PX en
// <th> se ignora (Dompdf lo trata como si no tuviera ancho), pero + ancho en
// % SÍ lo respeta. Por eso esto da "%", no píxeles, aunque el cálculo interno
// de fuente_una_linea()/ancho_columna_categoria() siga siendo en px.
function ancho_style($pct) { return 'width:'.round($pct, 2).'%'; }

// Tablas de 3.a/3.b: solo Marca (sin Segmento/Categoría, igual que el preview del navegador).
function tabla_marca_html($lineas, array $mesesActivos, array $mesesCorto, $valorFn, $anchoMarcaPct, $anchoMesesPct, $anchoTotalPct) {
	$rows = '';
	foreach ($lineas as $linea) {
		if ($linea['marca'] === '' || $linea['marca'] === null) continue;
		$valores = $valorFn($linea, $mesesActivos);
		$total = array_sum($valores);
		$rows .= '<tr><td>'.h($linea['marca']).'</td>';
		foreach ($valores as $v) $rows .= '<td class="num">'.moneda($v).'</td>';
		$rows .= '<td class="num">'.moneda($total).'</td></tr>';
	}
	if ($rows === '') {
		$colspanVacio = 1 + count($mesesActivos) + 1;
		$rows = '<tr><td colspan="'.$colspanVacio.'" class="vacio">Sin datos</td></tr>';
	}
	$anchoMesPct = count($mesesActivos) > 0 ? $anchoMesesPct / count($mesesActivos) : 0;
	$mesesHead = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesPct) {
		return '<th class="num" style="'.ancho_style($anchoMesPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));
	$marcaHead = '<th style="'.ancho_style($anchoMarcaPct).'">Marca</th>';
	$totalHead = '<th style="'.ancho_style($anchoTotalPct).'">Pago Total</th>';
	return [$rows, $marcaHead, $mesesHead, $totalHead];
}

function px($n, $escala) { return round($n * $escala, 2) . 'px'; }

// Data URI en vez de ruta relativa: evita depender de cómo Dompdf resuelve
// rutas de archivo en el servidor (más robusto, ya vimos que acá los
// problemas de "no llegó tal cual al servidor" son reales). El logo original
// es .webp (Dompdf no lo soporta bien) — se convirtió una vez a PNG.
//
// Dompdf necesita la extensión GD de PHP para insertar CUALQUIER imagen — sin
// ella, no solo no sale el logo, se cae toda la generación del PDF (probado:
// "The PHP GD extension is required, but is not installed."). Si no está
// disponible en el servidor, se omite el logo en vez de romper el acta entera.
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

if (!defined('ACTA_ANCHO_UTIL_PX')) define('ACTA_ANCHO_UTIL_PX', (210 - 40) * 96 / 25.4);

// Mide el ancho REAL del texto con el propio motor de fuentes de Dompdf (nada
// de un ratio de caracter "inventado" — eso fue lo que falló antes: subestimaba
// el ancho real de mayúsculas y el texto terminaba recortado por
// overflow:hidden, que además Dompdf no siempre respeta bien dentro de <td>).
function crear_medidor_texto() {
	$options = new \Dompdf\Options();
	$options->set('isRemoteEnabled', false);
	$dompdf = new \Dompdf\Dompdf($options);
	$fontMetrics = $dompdf->getFontMetrics();
	$font = $fontMetrics->getFont('DejaVu Sans', 'normal');
	return function ($texto, $tamanoFuente) use ($fontMetrics, $font) {
		if ($texto === '') return 0;
		$ancho = $font ? $fontMetrics->getTextWidth($texto, $font, $tamanoFuente) : 0;
		// Si el medidor real falla (ej. faltan archivos de fuente en el
		// servidor: vendor/dompdf/dompdf/lib/fonts pesa 8.4MB y puede subir
		// incompleta por WinSCP), NO confiar en un 0 falso — eso haría creer
		// que el texto no necesita ensanchar ni achicar nada. Se usa el
		// estimado anterior como red de seguridad en ese caso.
		if ($ancho <= 0) $ancho = mb_strlen($texto) * $tamanoFuente * 0.66;
		return $ancho;
	};
}

// *1.25 de margen de seguridad amplio: como no se puede verificar visualmente
// el PDF acá, se prefiere dejar bastante colchón a que quede "justo" y el
// texto se recorte de nuevo (ya pasó dos veces con márgenes más chicos).
function fuente_una_linea($texto, $fuenteBasePx, $anchoColPct, $medirTexto, $paddingPx = 10) {
	$anchoDisponible = (ACTA_ANCHO_UTIL_PX * $anchoColPct / 100 - $paddingPx) / 1.25;
	$anchoTexto = $medirTexto($texto, $fuenteBasePx);
	if ($anchoTexto <= $anchoDisponible) return $fuenteBasePx;
	// Sin piso "legible": la regla es una sola línea SIEMPRE, sin excepción.
	return $fuenteBasePx * ($anchoDisponible / $anchoTexto);
}

// Ensancha la columna Categoría según el nombre más largo de la tabla (en vez
// de achicar la letra contra un ancho fijo) — le resta ese % a las columnas
// de meses/totales, que necesitan mucho menos ancho para "$700.00" que para
// un nombre de categoría. anchoMinPct/anchoMaxPct limitan cuánto puede crecer
// para no dejar sin espacio a las demás columnas.
function ancho_columna_categoria(array $textos, $fuenteBasePx, $medirTexto, $anchoMinPct = 22, $anchoMaxPct = 48, $paddingPx = 10) {
	$anchoMaxTextoPx = 0;
	foreach ($textos as $t) $anchoMaxTextoPx = max($anchoMaxTextoPx, $medirTexto($t, $fuenteBasePx));
	if ($anchoMaxTextoPx === 0) return $anchoMinPct;
	$anchoNecesarioPx = $anchoMaxTextoPx * 1.25 + $paddingPx;
	$pct = ($anchoNecesarioPx / ACTA_ANCHO_UTIL_PX) * 100;
	return max($anchoMinPct, min($anchoMaxPct, $pct));
}

// OJO: "Sector" (Meta de Compras) y "% Participación" (Perchas) se ven en la
// vista previa del navegador pero no se guardan en repositorio_acuerdo_lineas
// (no existe esa columna) — por eso este PDF no las muestra.
//
// $escala reduce fuentes/espaciados en bloque para que quepa en 1 hoja A4 con
// márgenes de 2.5cm/3cm — la calcula generar_acta_pdf.php probando primero a
// escala 1 y bajando si Dompdf reporta más de 1 página (ver calcular_escala_una_pagina).
function generar_acta_html(array $detalle, $escala = 1.0, $medirTexto = null) {
	if ($medirTexto === null) $medirTexto = crear_medidor_texto();

	$logo = logo_base64();
	$logoHtml = $logo ? '<div style="text-align:center; margin-bottom:'.px(4, $escala).';"><img src="'.$logo.'" style="height:'.px(95, $escala).'; width:auto;"></div>' : '';

	$mesesCorto = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
	$mesesLargo = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

	$mesesActivos  = range($detalle['mes_inicio'], $detalle['mes_fin']);
	$cantidadMeses = count($mesesActivos);

	// 1ra pasada: texto de cada categoría, para saber cuánto tiene que crecer
	// esa columna antes de armar las filas (ver ancho_columna_categoria()).
	$categoriaTextos = array_map(function ($linea) {
		return trim($linea['segmento'].' '.$linea['categoria'].' '.$linea['marca']);
	}, $detalle['lineas']['meta_compra']);
	$categoriaPct = round(ancho_columna_categoria($categoriaTextos, 12 * $escala, $medirTexto), 2);
	$restoPct = 100 - $categoriaPct;
	$mesesPct    = round(34 * $restoPct / 74, 2);
	$totalPct    = round(16 * $restoPct / 74, 2);
	$rebatePct   = round(8 * $restoPct / 74, 2);
	$estimadoPct = round(16 * $restoPct / 74, 2);

	$metaRows = ''; $metaSums = array_fill(0, $cantidadMeses, 0.0); $metaGrandTotal = 0.0; $metaGrandEst = 0.0;
	foreach ($detalle['lineas']['meta_compra'] as $i => $linea) {
		$valores = valores_por_mes($linea, $mesesActivos);
		foreach ($valores as $j => $v) $metaSums[$j] += $v;
		$total  = array_sum($valores);
		$rebate = (float) $linea['rebate_pct'];
		$est    = $total * (1 + $rebate);
		$metaGrandTotal += $total; $metaGrandEst += $est;

		$categoriaTexto = $categoriaTextos[$i];
		$fuenteCategoria = fuente_una_linea($categoriaTexto, 12 * $escala, $categoriaPct, $medirTexto);
		// Una sola línea horizontal SIEMPRE (requisito explícito, sin excepción):
		// nowrap fuerza 1 línea y el tamaño ya viene calculado para que quepa.
		$metaRows .= '<tr><td style="white-space:nowrap; overflow:hidden; font-size:'.round($fuenteCategoria, 2).'px;">'.h($categoriaTexto).'</td>';
		foreach ($valores as $v) $metaRows .= '<td class="num">'.moneda($v).'</td>';
		$metaRows .= '<td class="num">'.moneda($total).'</td>';
		$metaRows .= '<td class="ctr rebate-cell">'.number_format($rebate * 100, 1).'%</td>';
		$metaRows .= '<td class="num">'.moneda($est).'</td></tr>';
	}
	if ($metaRows === '') {
		$colspanMeta = 1 + $cantidadMeses + 3;
		$metaRows = '<tr><td colspan="'.$colspanMeta.'" class="vacio">Sin datos</td></tr>';
	}
	$anchoMesMetaPct = $cantidadMeses > 0 ? $mesesPct / $cantidadMeses : 0;
	$mesesHeadHtml = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesMetaPct) {
		return '<th class="num" style="'.ancho_style($anchoMesMetaPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));

	$cabecerasValorFn = function ($linea, $mesesActivos) { return valores_por_mes($linea, $mesesActivos); };
	list($cabecerasRows, $marcaHeadCab, $mesesHeadCab, $totalHeadCab) = tabla_marca_html($detalle['lineas']['cabecera'], $mesesActivos, $mesesCorto, $cabecerasValorFn, 20, 62, 18);

	$rumaValorFn = function ($linea, $mesesActivos) { return array_fill(0, count($mesesActivos), (float) $linea['valor_mensual_unico']); };
	list($rumasRows, $marcaHeadRuma, $mesesHeadRuma, $totalHeadRuma) = tabla_marca_html($detalle['lineas']['ruma'], $mesesActivos, $mesesCorto, $rumaValorFn, 20, 62, 18);

	$rumaLegendRows = '';
	$marcasVistas = [];
	foreach ($detalle['lineas']['ruma'] as $linea) {
		if ($linea['marca'] === '' || isset($marcasVistas[$linea['marca']])) continue;
		$marcasVistas[$linea['marca']] = true;
		$rumaLegendRows .= '<tr><td>'.h($linea['marca']).'</td><td class="num">'.moneda($linea['valor_mensual_unico']).'</td></tr>';
	}
	if ($rumaLegendRows === '') $rumaLegendRows = '<tr><td colspan="2" class="vacio">Sin datos</td></tr>';

	// Sin subtítulo propio a propósito: va bajo el título combinado "3.b.
	// Espacio en Perchas & Rumas" (el usuario pidió sacar el título "3.c",
	// no la tabla).
	$anchoMesPerchaPct = $cantidadMeses > 0 ? 38 / $cantidadMeses : 0;
	$perchaRows = ''; $mesesHeadPercha = implode('', array_map(function ($m) use ($mesesCorto, $anchoMesPerchaPct) {
		return '<th class="num" style="'.ancho_style($anchoMesPerchaPct).'">'.$mesesCorto[$m].'</th>';
	}, $mesesActivos));
	foreach ($detalle['lineas']['percha'] as $linea) {
		if ($linea['marca'] === '') continue;
		$valores = valores_por_mes($linea, $mesesActivos);
		$total = array_sum($valores);
		$perchaRows .= '<tr><td>'.h($linea['marca']).'</td><td class="ctr">'.h($linea['participacion'] !== '' ? $linea['participacion'] : '—').'</td><td class="ctr">'.(int) $linea['cantidad_max_percha'].'</td>';
		foreach ($valores as $v) $perchaRows .= '<td class="num">'.moneda($v).'</td>';
		$perchaRows .= '<td class="num">'.moneda($total).'</td></tr>';
	}
	if ($perchaRows === '') {
		$colspanPercha = 3 + $cantidadMeses + 1;
		$perchaRows = '<tr><td colspan="'.$colspanPercha.'" class="vacio">Sin datos</td></tr>';
	}

	$periodoTexto = implode(' ', array_map(function ($m) use ($mesesLargo) { return $mesesLargo[$m]; }, $mesesActivos));
	$fechaTexto   = $detalle['fecha_generacion'] ? date('d/m/Y', strtotime($detalle['fecha_generacion'])) : '—';

	$html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
@page { size: A4; margin: 2.5cm 2cm; }
* { box-sizing: border-box; }
p, h1, ul { margin: 0 0 '.px(3, $escala).'; }
body { font-family: "DejaVu Sans", sans-serif; font-size: '.px(10.5, $escala).'; color: #1a1b22; line-height: 1.5; }
h1 { font-size: '.px(17, $escala).'; text-align: center; text-transform: uppercase; margin: '.px(3, $escala).' 0 '.px(5, $escala).'; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td, th { padding: '.px(2.5, $escala).' '.px(6, $escala).'; word-wrap: break-word; }
.num { text-align: right; }
.ctr { text-align: center; }
.vacio { text-align: center; color: #757684; padding: '.px(6, $escala).' !important; }
.doc-no { position: fixed; top: '.px(14, $escala).'; right: '.px(14, $escala).'; text-align: right; font-size: '.px(10, $escala).'; }
.doc-no strong { display: block; font-size: '.px(14, $escala).'; }
.meta-tabla td, .meta-tabla th { border: 1px solid #c4c5d5; }
.meta-tabla thead th { background: #eeedf7; }
.total-row td { font-weight: bold; border-top: 2px solid #1a1b22; }
.rebate-cell { background: #fbf0cf; }
.label { font-size: '.px(9, $escala).'; text-transform: uppercase; letter-spacing: 0.05em; color: #444653; }
.hint { font-size: '.px(9.5, $escala).'; color: #444653; margin: 0 0 '.px(2, $escala).'; }
.subtitulo { font-size: '.px(12.5, $escala).'; text-transform: uppercase; margin: '.px(6, $escala).' 0 '.px(2, $escala).'; font-weight: bold; }
.condiciones { background: #f4f2fc; border: 1px solid #c4c5d5; border-radius: 6px; padding: '.px(5, $escala).' '.px(9, $escala).'; margin-top: '.px(3, $escala).'; }
.condiciones h3 { font-size: '.px(10, $escala).'; text-transform: uppercase; margin: 0 0 '.px(2, $escala).'; }
.condiciones ul { margin: 0; padding-left: '.px(16, $escala).'; }
.condiciones li { margin-bottom: '.px(1, $escala).'; }
.firma-linea-firmar { border-bottom: 1px solid #1a1b22; height: '.px(34, $escala).'; }
.legend-box { border: 1px solid #c4c5d5; border-radius: 4px; padding: '.px(4, $escala).'; }
.legend-box th, .legend-box td { font-size: '.px(9.5, $escala).'; }
</style></head><body>

<div class="doc-no"><span class="label">Documento No:</span><strong>'.h($detalle['documento_no']).'</strong></div>
'.$logoHtml.'
<h1>Acuerdo de Desarrollo de Negocios Canal Directo</h1>

<table style="border-top:1px solid #757684; border-bottom:1px solid #757684; margin-bottom:'.px(3, $escala).';"><tr>
	<td style="border:none; width:34%;"><span class="label">Estimado(a)</span><br><strong>'.h($detalle['distribuidor']).'</strong></td>
	<td style="border:none; width:33%;"><span class="label">Localidad</span><br><strong>'.h($detalle['localidad']).'</strong></td>
	<td style="border:none; width:33%;"><span class="label">Fecha</span><br><strong>'.h($fechaTexto).'</strong></td>
</tr></table>

<p>JABONERÍA WILSON S.A. y '.h($detalle['distribuidor']).' celebran el presente acuerdo de desarrollo de negocios para el fortalecimiento mutuo en el mercado regional.</p>
<p><span class="label">Periodo del acuerdo</span> <strong>'.h($periodoTexto).'</strong></p>

<p class="subtitulo">1. Meta de Compras en Dólares</p>
<p class="hint">Dólares comprados por categoría sin considerar bonificación/descuentos.</p>
<table class="meta-tabla">
	<thead><tr><th style="'.ancho_style($categoriaPct).'">Categoría</th>'.$mesesHeadHtml.'<th style="'.ancho_style($totalPct).'">Total Período</th><th style="'.ancho_style($rebatePct).'">Rebate</th><th style="'.ancho_style($estimadoPct).'">Estimado a Ganar</th></tr></thead>
	<tbody>'.$metaRows.'</tbody>
	<tfoot><tr class="total-row"><td>Total</td>';
	foreach ($metaSums as $s) $html .= '<td class="num">'.moneda($s).'</td>';
	$html .= '<td class="num">'.moneda($metaGrandTotal).'</td><td class="ctr">—</td><td class="num">'.moneda($metaGrandEst).'</td></tr></tfoot>
</table>

<div class="condiciones">
	<h3>Condiciones</h3>
	<ul>
		<li><strong>a)</strong> Cumplir con la meta del período en dólares netos al 100%.</li>
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

<p class="subtitulo">3.a. Extravisibilidad: Cabeceras</p>
<p class="hint">Son prestaciones del cliente y por el cual se define un valor fijo a cancelar según el cuadro.<br>Se cancelará el valor acordado si, durante todo el período del acuerdo, se mantiene el o los espacios acordados.<br>En el caso de desabastecimientos y se incumple con el espacio acordado durante el lapso mínimo de 7 días, la bonificación total del mes no será cancelada.</p>
<table class="meta-tabla">
	<thead><tr>'.$marcaHeadCab.$mesesHeadCab.$totalHeadCab.'</tr></thead>
	<tbody>'.$cabecerasRows.'</tbody>
</table>

<p class="subtitulo">3.b. Espacio en Perchas &amp; Rumas</p>
<p class="hint">Se cancelará el valor acordado si, durante todo el período del acuerdo, las categorías mantienen el espacio acordado. La participación se considerará por número de caras/display.<br>En el caso de desabastecimientos y se incumple con el espacio acordado durante el lapso mínimo de 7 días, la bonificación total del mes no será cancelada.<br>El espacio debe estar demarcado con preciadores, polipasacalle, cenefas y cualquier otro elemento de visibilidad.</p>
<table style="border:none;"><tr>
	<td style="border:none; width:78%; vertical-align:top; padding:0;">
		<table class="meta-tabla">
			<thead><tr>'.$marcaHeadRuma.$mesesHeadRuma.$totalHeadRuma.'</tr></thead>
			<tbody>'.$rumasRows.'</tbody>
		</table>
	</td>
	<td style="border:none; width:2%;"></td>
	<td style="border:none; width:20%; vertical-align:top; padding:0;">
		<div class="legend-box">
			<span class="label">Valor Ruma x Marca x Mes</span>
			<table style="margin-top:'.px(4, $escala).';"><tbody>'.$rumaLegendRows.'</tbody></table>
		</div>
	</td>
</tr></table>

<table class="meta-tabla" style="margin-top:'.px(6, $escala).';">
	<thead><tr><th style="'.ancho_style(18).'">Marca</th><th style="'.ancho_style(14).'">% Participación</th><th style="'.ancho_style(10).'">Cantidad</th>'.$mesesHeadPercha.'<th style="'.ancho_style(20).'">Pago Total</th></tr></thead>
	<tbody>'.$perchaRows.'</tbody>
</table>

<p class="subtitulo">Consideraciones Generales</p>
<p style="margin:'.px(3, $escala).' 0;">Al cierre de cada mes, usted nos facilitará la información de su inventario. <strong>OBLIGATORIO</strong>.</p>
<p style="margin:'.px(3, $escala).' 0;">La liquidación del acuerdo se realizará al finalizar el periodo. El pago total será reconocido a través de nota de crédito. El plazo para emitir la nota de crédito es hasta 2 meses luego de finalizar el periodo del acuerdo.</p>
<p style="margin:'.px(3, $escala).' 0;">Como constancia del presente convenio, firman de común acuerdo las partes.</p>

<table style="border:none; margin-top:'.px(30, $escala).';"><tr>
	<td style="border:none; width:50%; text-align:center; padding-right:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:'.px(1, $escala).' 0 0; font-weight:bold;">Nombre: ________________________________________</p>
		<p class="label" style="margin:0;">Ejecutivo Comercial</p>
	</td>
	<td style="border:none; width:50%; text-align:center; padding-left:16px;">
		<div class="firma-linea-firmar"></div>
		<p style="margin:'.px(1, $escala).' 0 0; font-weight:bold;">Nombre: ________________________________________</p>
		<p class="label" style="margin:0;">Jefe Comercial</p>
	</td>
</tr></table>

<div style="text-align:center; margin-top:'.px(10, $escala).';">
	<p style="margin:0;">Jabonería Wilson<br><strong>ACEPTACIÓN DEL PRESENTE CONVENIO POR PARTE DEL CLIENTE</strong></p>
	<p style="font-size:'.px(10, $escala).'; color:#444653;">El CLIENTE declara expresamente que ha suscrito este Acuerdo a su entera satisfacción y entendimiento, de manera libre y voluntaria, por lo que nada tiene que reclamar sobre el contenido, la aplicación y/o ejecución del mismo.</p>
	<div class="firma-linea-firmar" style="width:'.px(220, $escala).'; margin:'.px(14, $escala).' auto 0;"></div>
	<p class="label" style="margin:'.px(2, $escala).' 0 0;">Firma del Cliente</p>
	<p class="label" style="margin-top:'.px(6, $escala).';">Razón Social:</p>
	<p style="font-weight:bold; margin:0;">'.h($detalle['distribuidor']).'</p>
</div>

</body></html>';

	return $html;
}
?>
