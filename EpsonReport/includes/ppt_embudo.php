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
		if (!$m) {
			ep_ppt_quitar($xp, $nomTxt);
			ep_ppt_quitar($xp, $nomNum);
			ep_ppt_quitar($xp, $nomBarra);
			ep_ppt_quitar($xp, $nomFondo);
			continue;
		}
		// Un modelo largo se recorta con "..." antes de llegar a donde empieza la barra, para no montarse encima.
		$nombreModelo = ep_ppt_mayus($m['modelo']);
		$medLabel = ep_ppt_medidas($xp, $nomTxt);
		$medBarra = ep_ppt_medidas($xp, $nomFondo);
		if ($medLabel && $medBarra) {
			$nombreModelo = ep_ppt_recortar_ancho($nombreModelo, $medBarra[0] - $medLabel[0] - 40000, 8);
		}
		ep_ppt_texto($dom, $xp, $nomTxt, [$nombreModelo]);
		ep_ppt_texto($dom, $xp, $nomNum, [(string) $m['cantidad']]);
		ep_ppt_estilo_barra($xp, $nomFondo, false);
		ep_ppt_estilo_barra($xp, $nomBarra, true);
		ep_ppt_ancho($xp, $nomFondo, $largoMax);
		ep_ppt_ancho($xp, $nomBarra, (int) round($largoMax * ((int) $m['cantidad']) / $maxCant));
		ep_ppt_posicion_x($xp, $nomNum, 6501235 + $largoMax + 40000);
	}
	$mayor = $modelos[0] ?? null;
	$menor = !empty($modelos) ? $modelos[count($modelos) - 1] : null;
	// Las dos tarjetas traían ancho distinto en la plantilla (una se quedaba corta); se igualan a la más ancha, y aun así
	// un nombre largo se recorta con "..." en vez de pasar a una 2da línea, que se montaba con "SKU CON MAYOR/MENOR VENTA".
	$medMayor = ep_ppt_medidas($xp, $n['mayor'][0]);
	$medMenor = ep_ppt_medidas($xp, $n['menor'][0]);
	$anchoSku = max($medMayor[2] ?? 0, $medMenor[2] ?? 0);
	if ($anchoSku > 0) {
		ep_ppt_ancho($xp, $n['mayor'][0], $anchoSku);
		ep_ppt_ancho($xp, $n['menor'][0], $anchoSku);
	}
	// Texto grande en negrita: cada letra ocupa más que en las filas de 8pt, por eso el ancho por carácter es mayor aquí.
	$textoSku = fn(?array $m) => $m ? ep_ppt_recortar_ancho(ep_ppt_mayus($m['modelo']), $anchoSku ?: 1500000, 14, 0.75) : 'SIN DATOS';
	ep_ppt_texto($dom, $xp, $n['mayor'][0], [$textoSku($mayor)]);
	ep_ppt_texto($dom, $xp, $n['mayor'][1], [$mayor ? ep_ppt_pct(rtrim($mayor['pct'], '%')) : ep_ppt_pct(0)]);
	ep_ppt_texto($dom, $xp, $n['menor'][0], [$textoSku($menor)]);
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
	$derecha = $n['comentarios_derecha'] ?? null;
	ep_ppt_comentarios($dom, $xp, $n['comentarios'], (array) ($reg['comentarios'] ?? []), $derecha ? EP_PPT_COM_DER[2] - 196096 : 3009751);
	if ($derecha) {
		ep_ppt_comentarios_a_la_derecha($xp, $derecha['card'], $derecha['titulo'], $n['comentarios'][0]);
	}

	if (!empty($n['titulo'])) {
		ep_ppt_ingresos_modelo($dom, $xp, $n, $reg['modelos'] ?? []);
	}
}

// Tarjeta de comentarios en la columna derecha, alta: [x, y, ancho, alto] (de la fila de tarjetas al pie del embudo).
const EP_PPT_COM_DER = [9239700, 1045029, 2633300, 5574877];

// Activaciones: comentarios pasan a la columna derecha y dejan su lugar a "Ingresos por Modelo".
function ep_ppt_comentarios_a_la_derecha(DOMXPath $xp, string $card, string $titulo, string $texto): void {
	[$x, $y, $cx, $cy] = EP_PPT_COM_DER;
	ep_ppt_geometria($xp, $card, $x, $y, $cx, $cy);
	ep_ppt_geometria($xp, $titulo, $x + 91193, $y + 170000, $cx - 180000, 261610);
	ep_ppt_geometria($xp, $texto, $x + 98048, $y + 1100000, $cx - 196096, 215444);
}

// Ingresos por modelo (cantidad × precio): clona el título y las filas de "Detalle de Ventas" y las mueve a la franja
// libre de la derecha de la plantilla, para no tocar el diseño oficial. Solo Activaciones y Epson Day traen precio;
// si ningún modelo lo tiene, no se agrega nada.
function ep_ppt_ingresos_modelo(DOMDocument $dom, DOMXPath $xp, array $n, array $modelos): void {
	$conPrecio = array_values(array_filter($modelos, fn($m) => (float) ($m['precio'] ?? 0) > 0));
	if (empty($conPrecio)) {
		return;
	}
	usort($conPrecio, fn($a, $b) => ($b['cantidad'] * $b['precio']) <=> ($a['cantidad'] * $a['precio']));
	$conPrecio = array_slice($conPrecio, 0, 5);
	// No cabían las dos tarjetas completas una junto a otra: "Detalle de Ventas" se corre un poco a la izquierda
	// (hay margen antes de las tarjetas de SKU mayor/menor) y la de ingresos queda fija en su posición actual: $deltaX
	// se recalcula a partir de ese destino fijo para que $corrimiento solo mueva "Detalle de Ventas", nunca "Ingresos".
	// Con comentarios a la derecha (Activaciones), "Ingresos" baja al lugar que dejaron, debajo de "Detalle de Ventas" y alineada con ella.
	$debajo = !empty($n['comentarios_derecha']);
	$corrimiento = $debajo ? 0 : -160000;
	$ingresosLeftFijo = $debajo ? 0 : 3129982; // posición (relativa a la tarjeta original) donde debe quedar "Ingresos por Modelo"
	$anchoIngresosCard = $debajo ? 3209982 : 3059982; // no se pasa del borde de la diapositiva
	$deltaX = $ingresosLeftFijo - $corrimiento;
	$deltaY = $debajo ? 2128967 : 0; // de la tarjeta de "Detalle de Ventas" al lugar que dejó la de comentarios
	$id = 90000;
	if ($corrimiento !== 0) {
		if (!empty($n['card'])) {
			ep_ppt_mover_x($xp, $n['card'], $corrimiento);
		}
		ep_ppt_mover_x($xp, $n['titulo'], $corrimiento);
		foreach ($n['filas'] as [$nomTxt2, $nomNum2, $nomBarra2]) {
			ep_ppt_mover_x($xp, $nomTxt2, $corrimiento);
			ep_ppt_mover_x($xp, $nomNum2, $corrimiento);
			$grupo2 = ep_ppt_grupo_de($xp, $nomBarra2);
			if ($grupo2) {
				ep_ppt_mover_x($xp, $grupo2, $corrimiento);
			}
		}
	}

	// La tarjeta (imagen de fondo con borde y sombra) va primero, para que quede detrás del título y las filas.
	if (!empty($n['card'])) {
		ep_ppt_clonar_y_mover($xp, $n['card'], 'Ingresos Card', $deltaX, [], $id++, $deltaY);
		ep_ppt_ancho($xp, 'Ingresos Card', $anchoIngresosCard);
	}
	if (ep_ppt_clonar_y_mover($xp, $n['titulo'], 'Ingresos Titulo', $deltaX, [], $id++, $deltaY)) {
		ep_ppt_texto($dom, $xp, 'Ingresos Titulo', ['INGRESOS POR MODELO']);
	}
	$maximo = max(1, ...array_map(fn($m) => $m['cantidad'] * $m['precio'], $conPrecio));
	// La barra queda más corta que la de "Detalle de Ventas" (que es un simple conteo) porque un monto en dólares
	// ocupa más espacio; así el número, puesto siempre justo después del final de la barra, nunca se le monta encima.
	$largoMax = 1400000;
	$anchoNumero = 750000;
	$gutter = 40000;
	foreach ($n['filas'] as $i => [, , $nomBarra, $nomFondo]) {
		$m = $conPrecio[$i] ?? null;
		if (!$m) {
			continue; // sin dato para esta fila: no se clona nada, no queda fila vacía
		}
		$nvNombre = 'Ingresos Nombre '.$i;
		$nvNumero = 'Ingresos Numero '.$i;
		$nvFondo = 'Ingresos Fondo '.$i;
		$nvBarra = 'Ingresos Barra '.$i;
		$grupoOrigen = ep_ppt_grupo_de($xp, $nomBarra);
		ep_ppt_clonar_y_mover($xp, $n['filas'][$i][0], $nvNombre, $deltaX, [], $id++, $deltaY);
		// El grupo (fondo+barra) se clona antes que el número: si no, el fondo (siempre a ancho completo) tapa el monto.
		if ($grupoOrigen) {
			ep_ppt_clonar_y_mover($xp, $grupoOrigen, 'Ingresos Grupo '.$i, $deltaX, [$nomFondo => $nvFondo, $nomBarra => $nvBarra], $id, $deltaY);
		}
		$id += 3;
		ep_ppt_clonar_y_mover($xp, $n['filas'][$i][1], $nvNumero, $deltaX, [], $id++, $deltaY);
		$ingreso = $m['cantidad'] * $m['precio'];
		// Igual que en "Detalle de Ventas": recorta antes de llegar a donde empieza la barra clonada (mismo hueco relativo, se movieron juntas).
		// El fondo/barra viven dentro de un grupo: clonar un grupo solo mueve su ancla (ep_ppt_clonar_y_mover), nunca la posición
		// interna de sus hijos — hay que sumarle $deltaX a lo que se lea, o la comparación sale con la posición vieja, sin mover.
		$nombreModelo = ep_ppt_mayus($m['modelo']);
		$medLabel = ep_ppt_medidas($xp, $nvNombre);
		$medBarra = $grupoOrigen ? ep_ppt_medidas($xp, $nvFondo) : null;
		if ($medLabel && $medBarra) {
			$nombreModelo = ep_ppt_recortar_ancho($nombreModelo, ($medBarra[0] + $deltaX) - $medLabel[0] - 40000, 8);
		}
		ep_ppt_texto($dom, $xp, $nvNombre, [$nombreModelo]);
		ep_ppt_texto($dom, $xp, $nvNumero, ['$'.number_format($ingreso, 2)]);
		ep_ppt_sin_autoajuste($xp, $nvNumero);
		// El número va siempre justo después del final de la barra (a todo lo largo, no según cuánto esté rellena), nunca se monta encima.
		$medidas = ep_ppt_medidas($xp, $nvNumero);
		if ($medidas) {
			[, $y, , $cy] = $medidas;
			ep_ppt_geometria($xp, $nvNumero, 6415448 + $ingresosLeftFijo + $largoMax + $gutter, $y, $anchoNumero, $cy);
		}
		if ($grupoOrigen) {
			ep_ppt_estilo_barra($xp, $nvFondo, false);
			ep_ppt_estilo_barra($xp, $nvBarra, true);
			ep_ppt_ancho($xp, $nvFondo, $largoMax);
			ep_ppt_ancho($xp, $nvBarra, (int) round($largoMax * $ingreso / $maximo));
		}
	}
}
