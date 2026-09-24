<?php
// PPTX de Exhibiciones que inspiran (plantilla recursos/ppt/exhibiciones.pptx): detalle por tipo de exhibición y comentarios.

require_once __DIR__.'/ppt_motor.php';

function ep_ppt_exhibiciones_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/exhibiciones.pptx',
		'titulo' => 'EXHIBICIONES QUE INSPIRAN',
		'prefijo_actividad' => 'EXHIBICION',
		'stats' => ['n' => 3, 'llenar' => 'ep_ppt_exhibiciones_estadisticas', 'ensanchar' => 1.5],
		'fotos' => [
			'n' => 4,
			'titulo' => 'CuadroTexto 1',
			'rects' => ['Rectángulo 3', 'Rectángulo 4', 'Rectángulo 5'],
			'orden' => ['exhibicion-1', 'exhibicion-2', 'exhibicion-3'],
		],
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_exhibiciones(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_exhibiciones_spec(), $registros, $tituloMes, $opciones);
}

// Estadísticas de un registro: cinco tipos de exhibición en el orden de la plantilla, cada uno con su barra, y comentarios.
function ep_ppt_exhibiciones_estadisticas(DOMDocument $dom, DOMXPath $xp, array $reg): void {
	$x = $reg['exhibiciones'] ?? [];
	// [número, barra, fondo, cantidad]: los textos de cada fila ya vienen en la plantilla.
	$filas = [
		['CuadroTexto 141', 'Rectángulo 115', 'Rectángulo 114', (int) ($x['cabeceras'] ?? 0)],
		['CuadroTexto 145', 'Rectángulo 131', 'Rectángulo 130', (int) ($x['rumas'] ?? 0)],
		['CuadroTexto 144', 'Rectángulo 134', 'Rectángulo 133', (int) ($x['muebles'] ?? 0)],
		['CuadroTexto 143', 'Rectángulo 137', 'Rectángulo 136', (int) ($x['exh_regular'] ?? 0)],
		['CuadroTexto 142', 'Rectángulo 140', 'Rectángulo 139', (int) ($x['otras'] ?? 0)],
	];
	$maximo = max(1, ...array_column($filas, 3));
	$largoMax = 2000000; // el número queda a la derecha de la barra, no encima
	foreach ($filas as [$nomNum, $nomBarra, $nomFondo, $cantidad]) {
		ep_ppt_texto($dom, $xp, $nomNum, [(string) $cantidad]);
		ep_ppt_estilo_barra($xp, $nomFondo, false);
		ep_ppt_estilo_barra($xp, $nomBarra, true);
		ep_ppt_ancho($xp, $nomFondo, $largoMax);
		ep_ppt_ancho($xp, $nomBarra, (int) round($largoMax * $cantidad / $maximo));
	}
	// La tarjeta de comentarios de la plantilla es más angosta que la del detalle: se iguala.
	ep_ppt_ancho($xp, 'Gráfico 3', 4241843);
	ep_ppt_comentarios($dom, $xp, ['CuadroTexto 5', 'CuadroTexto 6', 'CuadroTexto 7', 'CuadroTexto 9', 'CuadroTexto 10'], (array) ($reg['comentarios'] ?? []), 4045747);
}
