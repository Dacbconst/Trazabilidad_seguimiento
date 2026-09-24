<?php
// PPTX de Capacitaciones (plantilla recursos/ppt/capacitaciones.pptx): el armado general lo hace ppt_motor.php; aquí van
// la especificación y las estadísticas de cada registro (asistentes por cargo e interacciones).

require_once __DIR__.'/ppt_motor.php';

function ep_ppt_capacitaciones_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/capacitaciones.pptx',
		'titulo' => 'CAPACITACIONES',
		'prefijo_actividad' => 'CAPACITACION',
		'stats' => ['n' => 3, 'llenar' => 'ep_ppt_capacitaciones_estadisticas', 'ensanchar' => 1.5],
		'fotos' => [
			'n' => 4,
			'titulo' => 'CuadroTexto 1',
			'rects' => ['Rectángulo 4', 'Rectángulo 5', 'Rectángulo 6'],
			'orden' => ['equipo', 'capacitacion', 'entrega'],
		],
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_capacitaciones(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_capacitaciones_spec(), $registros, $tituloMes, $opciones);
}

// Estadísticas de un registro: detalle de asistentes por cargo, asistentes contra interacciones y comentarios.
function ep_ppt_capacitaciones_estadisticas(DOMDocument $dom, DOMXPath $xp, array $reg): void {
	$cap = $reg['capacitacion'] ?? [];
	$cargos = [
		(int) ($cap['vendedores'] ?? 0),
		(int) ($cap['jefe_tienda'] ?? 0),
		(int) ($cap['asistente_jefe'] ?? 0),
	];
	$asistentes = array_sum($cargos);
	$interacciones = (int) ($cap['interacciones'] ?? 0);

	// Detalle de asistentes: una barra por cargo, proporcional al total; el número queda a la derecha, fuera de la barra.
	$filas = [
		['CuadroTexto 36', 'Rectángulo 6', 'Rectángulo 5'],
		['CuadroTexto 40', 'Rectángulo 13', 'Rectángulo 12'],
		['CuadroTexto 39', 'Rectángulo 24', 'Rectángulo 18'],
	];
	$largoMax = 2051998;
	foreach ($filas as $i => [$nomNum, $nomBarra, $nomFondo]) {
		ep_ppt_texto($dom, $xp, $nomNum, [(string) $cargos[$i]]);
		ep_ppt_ancho($xp, $nomFondo, $largoMax);
		ep_ppt_estilo_barra($xp, $nomFondo, false);
		ep_ppt_estilo_barra($xp, $nomBarra, true);
		ep_ppt_ancho($xp, $nomBarra, $asistentes > 0 ? (int) round($largoMax * $cargos[$i] / $asistentes) : 0);
	}

	// Asistentes contra interacciones: dos barras verticales apoyadas en la misma base.
	$base = 4665000;
	$largoMaxVertical = 1349034;
	$largoInteracciones = $asistentes > 0 ? (int) round($largoMaxVertical * $interacciones / $asistentes) : 0;
	$largoInteracciones = $interacciones > 0 ? max($largoInteracciones, 15000) : 0;
	ep_ppt_texto($dom, $xp, 'CuadroTexto 165', [(string) $asistentes]);
	ep_ppt_texto($dom, $xp, 'CuadroTexto 166', [(string) $interacciones]);
	foreach ([['Rectángulo 155', $asistentes > 0 ? $largoMaxVertical : 0, 'CuadroTexto 165'], ['Rectángulo 158', $largoInteracciones, 'CuadroTexto 166']] as [$barra, $largo, $numero]) {
		ep_ppt_barra_vertical($xp, $barra, $largo, $base, 0.32);
		ep_ppt_posicion_y($xp, $numero, $base - $largo - 204000);
	}

	ep_ppt_comentarios($dom, $xp, ['CuadroTexto 46', 'CuadroTexto 47', 'CuadroTexto 48'], (array) ($reg['comentarios'] ?? []), 3529845);
}
