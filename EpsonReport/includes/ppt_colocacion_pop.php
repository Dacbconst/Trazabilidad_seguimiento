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
		],
	];
}

// Lo entregado en los registros, por campaña + material; el canal del punto de venta decide si suma a Retail o a Canales.
function ep_ppt_pop_agrupar(array $registros): array {
	$grupos = [];
	foreach ($registros as $reg) {
		$retail = strtoupper((string) ($reg['canal'] ?? '')) === 'RETAIL';
		foreach ($reg['pop_entregas'] ?? [] as $e) {
			$clave = ($reg['campana'] ?? '').'|'.$e['material'];
			$grupos[$clave] ??= ['campana' => (string) ($reg['campana'] ?? ''), 'material' => $e['material'], 'canales' => 0, 'retail' => 0, 'entregas' => []];
			$grupos[$clave][$retail ? 'retail' : 'canales'] += (int) $e['cantidad'];
			$grupos[$clave]['entregas'][] = ['pdv' => ep_ppt_mayus((string) ($reg['punto_venta'] ?? '')), 'ciudad' => ep_ppt_mayus((string) ($reg['ciudad'] ?? '')), 'cantidad' => (int) $e['cantidad']];
		}
	}
	ksort($grupos);
	return $grupos;
}

// Diapositivas fijas del reporte: inventario (bodega del admin, canales y retail sumados) y detalle por punto de venta con subtotal por material.
function ep_ppt_pop_tablas(array &$ctx, array $registros, array $opciones): void {
	$grupos = ep_ppt_pop_agrupar($registros);
	if (!$grupos) {
		return;
	}
	$bodega = $opciones['pop_bodega'] ?? [];
	$recibido = [];
	foreach ($grupos as $clave => $g) {
		$b = (int) ($bodega[$clave] ?? 0);
		$recibido[] = ['celdas' => [$g['material'], $g['campana'], 'UNIDADES', $b, $g['canales'], $g['retail'], $b - $g['canales'] - $g['retail']]];
	}
	$colRecibido = [['', 0.2, 'r'], ['CAMPAÑA', 0.13, 'ctr'], ['TIPO', 0.13, 'ctr'], ['BODEGA', 0.135, 'ctr'], ['CANALES', 0.135, 'ctr'], ['RETAIL', 0.135, 'ctr'], ['DISPONIBLE', 0.135, 'ctr']];
	ep_ppt_pop_paginas($ctx, 3, 'POP RECIBIDO', $colRecibido, $recibido);

	$detalle = [];
	foreach ($grupos as $g) {
		foreach ($g['entregas'] as $i => $e) {
			$detalle[] = ['celdas' => [$i === 0 ? $g['material'] : '', $g['campana'], $e['pdv'], $e['ciudad'], $e['cantidad']], 'grupo' => $g['material']];
		}
		$detalle[] = ['celdas' => ['', '', '', '', array_sum(array_column($g['entregas'], 'cantidad'))], 'negrita' => true];
	}
	$colDetalle = [['', 0.2, 'r'], ['CAMPAÑA', 0.15, 'ctr'], ['PDV', 0.3, 'ctr'], ['CIUDAD', 0.15, 'ctr'], ['CANTIDAD ENTREGADA', 0.2, 'ctr']];
	ep_ppt_pop_paginas($ctx, 4, 'DETALLE POP RECIBIDO', $colDetalle, $detalle);
}

// Reparte las filas en diapositivas clonadas de la plantilla $n: hasta dos bloques por diapositiva, lado a lado.
function ep_ppt_pop_paginas(array &$ctx, int $n, string $titulo, array $columnas, array $filas): void {
	[$dom, $xp] = ep_ppt_cargar((string) $ctx['tpl']->getFromName('ppt/slides/slide'.$n.'.xml'));
	$area = ep_ppt_medidas($xp, 'Area tabla');
	$capacidad = ep_ppt_tabla_capacidad($area ? $area[3] : 5000000);
	foreach (array_chunk($filas, $capacidad * 2) as $pagina) {
		$bloques = array_chunk($pagina, $capacidad);
		// Un material partido entre columnas o diapositivas repite su nombre al empezar el bloque nuevo.
		foreach ($bloques as &$bloque) {
			if (isset($bloque[0]['grupo']) && $bloque[0]['celdas'][0] === '') {
				$bloque[0]['celdas'][0] = $bloque[0]['grupo'];
			}
		}
		unset($bloque);
		$slide = ep_ppt_slide_nueva($ctx, $n);
		ep_ppt_tabla_en_recuadro($slide['dom'], $slide['xp'], 'Area tabla', $titulo, $columnas, $bloques);
		ep_ppt_slide_guardar($ctx, $slide);
	}
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_colocacion_pop(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_colocacion_pop_spec(), $registros, $tituloMes, $opciones);
}
