<?php
// PPTX de Colocación de POP (formato oficial de Epson): tablas "POP RECIBIDO" y "DETALLE POP RECIBIDO" una vez por reporte, y fotos por punto de venta.

require_once __DIR__.'/ppt_motor.php';
require_once __DIR__.'/ppt_tabla.php';

function ep_ppt_colocacion_pop_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/colocacion-pop.pptx',
		'titulo' => 'COLOCACION DE POP',
		'prefijo_actividad' => 'POP',
		'fija' => 'ep_ppt_pop_tablas',
		'fotos' => [
			'n' => 5,
			'titulo' => 'CuadroTexto 2',
			'rects' => ['Rectángulo 4', 'Rectángulo 33', 'Rectángulo 34'],
			'orden' => ['implementacion-1', 'implementacion-2', 'implementacion-3'],
			// La foto se ve entera en su cuadro, sin recortar.
			'completa' => true,
		],
	];
}

// Lo entregado en los registros, por campaña + material; el canal del punto de venta decide si suma a Retail o a Canales.
function ep_ppt_pop_agrupar(array $registros, array $bodega = []): array {
	$grupos = [];
	// Todo lo que se cargó en bodega sale en la tabla, aunque todavía no tenga entregas.
	foreach (array_keys($bodega) as $clave) {
		[$campana, $material] = explode('|', (string) $clave, 2) + ['', ''];
		$grupos[$clave] = ['campana' => $campana, 'material' => $material, 'canales' => 0, 'retail' => 0, 'entregas' => []];
	}
	foreach ($registros as $reg) {
		foreach ($reg['pop_entregas'] ?? [] as $e) {
			$retail = strtoupper((string) ($e['canal'] ?? $reg['canal'] ?? '')) === 'RETAIL';
			// La campaña va en el material; la del registro es el respaldo de los registros anteriores a eso.
			$campana = (string) ($e['campana'] ?? $reg['campana'] ?? '');
			$clave = $campana.'|'.$e['material'];
			$grupos[$clave] ??= ['campana' => $campana, 'material' => $e['material'], 'canales' => 0, 'retail' => 0, 'entregas' => []];
			$grupos[$clave][$retail ? 'retail' : 'canales'] += (int) $e['cantidad'];
			$grupos[$clave]['entregas'][] = ['pdv' => ep_ppt_mayus((string) ($e['punto_venta'] ?? $reg['punto_venta'] ?? '')), 'ciudad' => ep_ppt_mayus((string) ($e['ciudad'] ?? $reg['ciudad'] ?? '')), 'cantidad' => (int) $e['cantidad']];
		}
	}
	ksort($grupos);
	return $grupos;
}

// Diapositivas fijas del reporte: inventario (bodega del admin, canales y retail sumados) y detalle por punto de venta con subtotal por material.
function ep_ppt_pop_tablas(array &$ctx, array $registros, array $opciones): void {
	$bodega = $opciones['pop_bodega'] ?? [];
	$grupos = ep_ppt_pop_agrupar($registros, $bodega);
	if (!$grupos) {
		return;
	}
	$recibido = [];
	foreach ($grupos as $clave => $g) {
		$b = (int) ($bodega[$clave] ?? 0);
		$recibido[] = ['celdas' => [$g['material'], $g['campana'], 'UNIDADES', $b, $g['canales'], $g['retail'], $b - $g['canales'] - $g['retail']]];
	}
	$colRecibido = [['', 0.2, 'r'], ['CAMPAÑA', 0.13, 'ctr'], ['TIPO', 0.13, 'ctr'], ['BODEGA', 0.135, 'ctr'], ['CANALES', 0.135, 'ctr'], ['RETAIL', 0.135, 'ctr'], ['DISPONIBLE', 0.135, 'ctr']];
	ep_ppt_pop_paginas($ctx, 3, 'POP RECIBIDO', $colRecibido, $recibido);

	$detalle = [];
	foreach ($grupos as $g) {
		if (!$g['entregas']) {
			continue;
		}
		foreach ($g['entregas'] as $i => $e) {
			$detalle[] = ['celdas' => [$i === 0 ? $g['material'] : '', $g['campana'], $e['pdv'], $e['ciudad'], $e['cantidad']], 'grupo' => $g['material']];
		}
		$detalle[] = ['celdas' => ['', '', '', '', array_sum(array_column($g['entregas'], 'cantidad'))], 'negrita' => true];
	}
	$colDetalle = [['', 0.2, 'r'], ['CAMPAÑA', 0.15, 'ctr'], ['PDV', 0.3, 'ctr'], ['CIUDAD', 0.15, 'ctr'], ['CANTIDAD ENTREGADA', 0.2, 'ctr']];
	ep_ppt_pop_paginas($ctx, 4, 'DETALLE POP RECIBIDO', $colDetalle, $detalle);
	ep_ppt_pop_flujo($ctx, $opciones['pop_flujo'] ?? []);
	ep_ppt_pop_peso($ctx, $opciones['pop_peso'] ?? []);
}

// Peso por cadena: unidades colocadas por cadena, su porcentaje del total y cuánto de sus puntos habilitados se cubrió (solo en reportes que lo congelaron al cerrar).
function ep_ppt_pop_peso(array &$ctx, array $peso): void {
	if (!$peso) {
		return;
	}
	$filas = [];
	foreach ($peso as $p) {
		$filas[] = ['celdas' => [ep_ppt_mayus($p['canal'] ?? ''), ep_ppt_mayus($p['cadena']), $p['habilitados'], $p['puntos'], $p['cobertura'] === null ? '-' : $p['cobertura'].'%', $p['unidades'], $p['peso'].'%']];
	}
	$habilitados = array_sum(array_column($peso, 'habilitados'));
	$puntos = array_sum(array_column($peso, 'puntos'));
	$filas[] = ['celdas' => ['', 'TOTAL', $habilitados, $puntos, $habilitados > 0 ? round($puntos / $habilitados * 100, 1).'%' : '-', array_sum(array_column($peso, 'unidades')), '100%'], 'negrita' => true];
	$columnas = [['CANAL', 0.14, 'ctr'], ['PDV', 0.24, 'ctr'], ['PUNTOS HABILITADOS', 0.14, 'ctr'], ['PUNTOS CON POP', 0.14, 'ctr'], ['COBERTURA', 0.12, 'ctr'], ['UNIDADES COLOCADAS', 0.12, 'ctr'], ['PESO', 0.1, 'ctr']];
	ep_ppt_pop_paginas($ctx, 4, 'PESO POR CADENA', $columnas, $filas, ['rejilla' => true, 'ancho' => 0.85, 'fila' => 250000, 'fuente' => 10], 1);
}

// Flujo del mes: lo que recibió cada supervisor y lo que cada promotor colocó en cada punto de venta (solo en reportes que lo congelaron al cerrar).
function ep_ppt_pop_flujo(array &$ctx, array $flujo): void {
	if (!$flujo) {
		return;
	}
	$filas = [];
	$anterior = '';
	$anteriorSup = '';
	foreach ($flujo as $f) {
		$clave = $f['campana'].'|'.$f['material'];
		$claveSup = $clave.'|'.$f['supervisor'];
		$sinColocar = $f['promotor'] === '';
		$filas[] = [
			'celdas' => [
				$clave === $anterior ? '' : ep_ppt_mayus($f['material']),
				$clave === $anterior ? '' : ep_ppt_mayus($f['campana']),
				$claveSup === $anteriorSup ? '' : ep_ppt_mayus($f['supervisor']),
				$claveSup === $anteriorSup ? '' : $f['recibido'],
				$sinColocar ? 'SIN COLOCAR' : ep_ppt_mayus($f['promotor']),
				ep_ppt_mayus($f['pdv']),
				ep_ppt_mayus($f['ciudad']),
				$sinColocar ? '' : $f['cantidad'],
			],
			'grupo' => ep_ppt_mayus($f['material']),
		];
		$anterior = $clave;
		$anteriorSup = $claveSup;
	}
	$columnas = [['MATERIAL', 0.12, 'ctr'], ['CAMPAÑA', 0.09, 'ctr'], ['SUPERVISOR', 0.15, 'ctr'], ['RECIBIÓ', 0.07, 'ctr'], ['PROMOTOR', 0.19, 'ctr'], ['PDV', 0.2, 'ctr'], ['CIUDAD', 0.1, 'ctr'], ['COLOCÓ', 0.08, 'ctr']];
	// Tabla completa (rejilla y filas alternadas) a todo el ancho, un solo bloque por diapositiva.
	ep_ppt_pop_paginas($ctx, 4, 'FLUJO DE ENTREGA', $columnas, $filas, ['rejilla' => true, 'ancho' => 1.0, 'fila' => 250000, 'fuente' => 10], 1);
}

// Reparte las filas en diapositivas clonadas de la plantilla $n: hasta $maxBloques bloques por diapositiva, lado a lado.
function ep_ppt_pop_paginas(array &$ctx, int $n, string $titulo, array $columnas, array $filas, array $estilo = [], int $maxBloques = 2): void {
	[$dom, $xp] = ep_ppt_cargar((string) $ctx['tpl']->getFromName('ppt/slides/slide'.$n.'.xml'));
	$area = ep_ppt_medidas($xp, 'Area tabla');
	$capacidad = ep_ppt_tabla_capacidad($area ? $area[3] : 5000000, $estilo);
	foreach (array_chunk($filas, $capacidad * $maxBloques) as $pagina) {
		$bloques = array_chunk($pagina, $capacidad);
		// Un material partido entre columnas o diapositivas repite su nombre al empezar el bloque nuevo.
		foreach ($bloques as &$bloque) {
			if (isset($bloque[0]['grupo']) && $bloque[0]['celdas'][0] === '') {
				$bloque[0]['celdas'][0] = $bloque[0]['grupo'];
			}
		}
		unset($bloque);
		$slide = ep_ppt_slide_nueva($ctx, $n);
		ep_ppt_tabla_en_recuadro($slide['dom'], $slide['xp'], 'Area tabla', $titulo, $columnas, $bloques, $estilo);
		ep_ppt_slide_guardar($ctx, $slide);
	}
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_colocacion_pop(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_colocacion_pop_spec(), ep_ppt_pop_fotos_por_punto($registros), $tituloMes, $opciones);
}

// Un registro con varios puntos sale como una entrada de fotos por punto (portada del punto como título), conservando sus entregas para las tablas.
function ep_ppt_pop_fotos_por_punto(array $registros): array {
	$salida = [];
	foreach ($registros as $reg) {
		$puntos = $reg['puntos'] ?? [];
		if (!$puntos) {
			$salida[] = $reg;
			continue;
		}
		foreach ($puntos as $i => $p) {
			$salida[] = array_merge($reg, [
				'pos_id' => $p['pos_id'] ?? '', 'punto_venta' => $p['punto_venta'] ?? '', 'ciudad' => $p['ciudad'] ?? '', 'canal' => $p['canal'] ?? '',
				'fotos' => $p['fotos'] ?? [],
				'pop_entregas' => $i === 0 ? ($reg['pop_entregas'] ?? []) : [],
				'puntos' => [],
			]);
		}
	}
	return $salida;
}
