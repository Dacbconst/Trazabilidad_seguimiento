<?php
// PPTX de Activaciones (plantilla recursos/ppt/activaciones.pptx): el armado general lo hace ppt_motor.php; aquí van solo
// la especificación de la actividad, el calendario del reporte (una vez) y las estadísticas de cada registro.

require_once __DIR__.'/ppt_embudo.php';

function ep_ppt_activaciones_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/activaciones.pptx',
		'titulo' => 'ACTIVACIONES',
		'prefijo_actividad' => 'ACTIVIDAD',
		'stats' => ['n' => 4, 'llenar' => 'ep_ppt_activaciones_estadisticas'],
		'fotos' => [
			'n' => 5,
			'titulo' => 'CuadroTexto 2',
			'rects' => ['Rectángulo 4', 'Rectángulo 33', 'Rectángulo 34'],
			'orden' => ['stand', 'interaccion-1', 'venta-1', 'interaccion-2', 'venta-2', 'venta-3'],
		],
		'fija' => 'ep_ppt_activaciones_calendario',
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
// $opciones: 'calendario_url' (imagen del calendario del reporte), 'programadas' (actividades programadas; vacío = igual a las ejecutadas) y 'solo_registro' (sin portada, título ni calendario).
function ep_ppt_activaciones(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_activaciones_spec(), $registros, $tituloMes, $opciones);
}

// Diapositiva 3 (una sola vez por reporte): calendario de activaciones y cumplimiento.
function ep_ppt_activaciones_calendario(array &$ctx, array $registros, array $opciones): void {
	$totalEjecutadas = count($registros);
	$programadas = isset($opciones['programadas']) && $opciones['programadas'] !== null ? (int) $opciones['programadas'] : $totalEjecutadas;
	$comentarios = [];
	foreach ($registros as $reg) {
		foreach ((array) ($reg['comentarios'] ?? []) as $comentario) {
			if (count($comentarios) < 3 && trim((string) $comentario) !== '') {
				$comentarios[] = ep_ppt_mayus((string) $comentario);
			}
		}
	}
	$slide = ep_ppt_slide_nueva($ctx, 3, 3);
	ep_ppt_texto($slide['dom'], $slide['xp'], 'CuadroTexto 12', [ep_ppt_pct($programadas > 0 ? $totalEjecutadas / $programadas * 100 : 0)]);
	ep_ppt_texto($slide['dom'], $slide['xp'], 'CuadroTexto 13', [$programadas.' ACTIVACIONES PROGRAMADAS', $totalEjecutadas.' EJECUTADAS']);
	foreach (['CuadroTexto 27', 'CuadroTexto 28', 'CuadroTexto 33'] as $i => $nombre) {
		ep_ppt_texto($slide['dom'], $slide['xp'], $nombre, [$comentarios[$i] ?? '']);
	}
	$urlCalendario = $opciones['calendario_url'] ?? '';
	if ($urlCalendario !== '' && isset($ctx['fotos'][$urlCalendario])) {
		ep_ppt_slide_imagen($ctx, $slide, 'Rectángulo 14', $ctx['fotos'][$urlCalendario]);
	} else {
		ep_ppt_quitar($slide['xp'], 'Rectángulo 14'); // sin imagen del calendario: se quita el cuadro de ejemplo
	}
	ep_ppt_slide_guardar($ctx, $slide);
}

// Estadísticas de un registro: las comunes del embudo, con los nombres de forma de la plantilla de Activaciones.
function ep_ppt_activaciones_estadisticas(DOMDocument $dom, DOMXPath $xp, array $reg): void {
	ep_ppt_estadisticas_embudo($dom, $xp, $reg, [
		'cobertura' => ['CuadroTexto 94', 'CuadroTexto 95'],
		'interaccion' => ['CuadroTexto 96', 'CuadroTexto 97'],
		'ventas' => ['CuadroTexto 101', 'CuadroTexto 102'],
		'visitaron' => 'CLIENTES QUE VISITARON LA TIENDA',
		'filas' => [
			['CuadroTexto 113', 'CuadroTexto 141', 'Rectángulo 115', 'Rectángulo 114'],
			['CuadroTexto 124', 'CuadroTexto 145', 'Rectángulo 131', 'Rectángulo 130'],
			['CuadroTexto 125', 'CuadroTexto 144', 'Rectángulo 134', 'Rectángulo 133'],
			['CuadroTexto 126', 'CuadroTexto 143', 'Rectángulo 137', 'Rectángulo 136'],
			['CuadroTexto 127', 'CuadroTexto 142', 'Rectángulo 140', 'Rectángulo 139'],
		],
		'mayor' => ['CuadroTexto 146', 'CuadroTexto 150'],
		'menor' => ['CuadroTexto 148', 'CuadroTexto 151'],
		'embudo' => [['Rectángulo 155', 'CuadroTexto 165'], ['Rectángulo 158', 'CuadroTexto 166'], ['Rectángulo 163', 'CuadroTexto 167']],
		'comentarios' => ['CuadroTexto 5', 'CuadroTexto 6', 'CuadroTexto 7', 'CuadroTexto 9', 'CuadroTexto 10'],
	]);
}
