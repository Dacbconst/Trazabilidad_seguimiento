<?php
// Estadísticas comunes de Activaciones, Epson Day y Evento o Ferias (misma diapositiva de Epson con otros nombres de forma):
// tarjetas de cobertura, interacciones y ventas, detalle por modelo, SKU mayor y menor, embudo de clientes y comentarios.

require_once __DIR__.'/ppt_motor.php';

// $n: nombres de forma de la plantilla. 'cobertura' puede faltar (Evento o Ferias no la tiene); 'visitaron' es el texto de esa fila.
function ep_ppt_estadisticas_embudo(DOMDocument $dom, DOMXPath $xp, array $reg, array $n): void {
	$emb = $reg['embudo'] ?? [];
	$cob = $reg['cobertura'] ?? [];
	$modelos = $reg['modelos'] ?? [];
	usort($modelos, fn($a, $b) => ($b['cantidad'] ?? 0) <=> ($a['cantidad'] ?? 0));

	if (!empty($n['cobertura'])) {
		ep_ppt_texto($dom, $xp, $n['cobertura'][0], [ep_ppt_pct($cob['pct'] ?? 0)]);
		ep_ppt_texto_tabulado($dom, $xp, $n['cobertura'][1], [['TIENDAS A NIVEL NACIONAL', (string) ($cob['nacional'] ?? 0)], ['TIENDAS COBERTURADAS', (string) ($cob['coberturadas'] ?? 0)]]);
	}
	ep_ppt_texto($dom, $xp, $n['interaccion'][0], [ep_ppt_pct($emb['tasa_interaccion_pct'] ?? 0)]);
	ep_ppt_texto_tabulado($dom, $xp, $n['interaccion'][1], [[$n['visitaron'], (string) ($emb['visitaron'] ?? 0)], ['CLIENTES QUE INTERACTUARON', (string) ($emb['interactuaron'] ?? 0)]]);
	ep_ppt_texto($dom, $xp, $n['ventas'][0], [ep_ppt_pct($emb['tasa_conversion_pct'] ?? 0)]);
	ep_ppt_texto_tabulado($dom, $xp, $n['ventas'][1], [['VENTAS REALIZADAS', (string) ($emb['compraron'] ?? 0)]]);

	// Detalle de ventas: hasta 5 modelos con su barra proporcional al más vendido.
	$maxCant = max(1, (int) ($modelos[0]['cantidad'] ?? 1));
	$largoMax = 2051998; // el fondo y la barra dejan libre el extremo derecho para el número
	foreach ($n['filas'] as $i => [$nomTxt, $nomNum, $nomBarra, $nomFondo]) {
		$m = $modelos[$i] ?? null;
		ep_ppt_texto($dom, $xp, $nomTxt, [$m ? ep_ppt_mayus($m['modelo']) : '']);
		ep_ppt_texto($dom, $xp, $nomNum, [$m ? (string) $m['cantidad'] : '']);
		ep_ppt_estilo_barra($xp, $nomFondo, false);
		ep_ppt_estilo_barra($xp, $nomBarra, true);
		ep_ppt_ancho($xp, $nomFondo, $largoMax);
		ep_ppt_ancho($xp, $nomBarra, $m ? (int) round($largoMax * ((int) $m['cantidad']) / $maxCant) : 0);
		ep_ppt_posicion_x($xp, $nomNum, 6501235 + $largoMax + 40000);
	}
	$mayor = $modelos[0] ?? null;
	$menor = !empty($modelos) ? $modelos[count($modelos) - 1] : null;
	ep_ppt_texto($dom, $xp, $n['mayor'][0], [$mayor ? ep_ppt_mayus($mayor['modelo']) : 'SIN DATOS']);
	ep_ppt_texto($dom, $xp, $n['mayor'][1], [$mayor ? ep_ppt_pct(rtrim($mayor['pct'], '%')) : ep_ppt_pct(0)]);
	ep_ppt_texto($dom, $xp, $n['menor'][0], [$menor ? ep_ppt_mayus($menor['modelo']) : 'SIN DATOS']);
	ep_ppt_texto($dom, $xp, $n['menor'][1], [$menor ? ep_ppt_pct(rtrim($menor['pct'], '%')) : ep_ppt_pct(0)]);

	// Embudo: clientes / interacciones / ventas, barras proporcionales a los clientes en tienda.
	$vis = max(1, (int) ($emb['visitaron'] ?? 0));
	$base = 6108000;
	$largoMaxEmbudo = 905791;
	$valores = [(int) ($emb['visitaron'] ?? 0), (int) ($emb['interactuaron'] ?? 0), (int) ($emb['compraron'] ?? 0)];
	foreach ($n['embudo'] as $i => [$barra, $numero]) {
		$largo = $i === 0 ? $largoMaxEmbudo : (int) round($largoMaxEmbudo * $valores[$i] / $vis);
		$largo = $i > 0 && $valores[$i] > 0 ? max($largo, 15000) : ($i > 0 ? 0 : $largo);
		ep_ppt_texto($dom, $xp, $numero, [(string) $valores[$i]]);
		ep_ppt_barra_vertical($xp, $barra, $largo, $base);
		ep_ppt_posicion_y($xp, $numero, $base - $largo - 201556);
	}
	ep_ppt_comentarios($dom, $xp, $n['comentarios'], (array) ($reg['comentarios'] ?? []), 3009751);
}
