<?php
// PPTX de Epson Day (plantilla recursos/ppt/epson-day.pptx): misma diapositiva que Activaciones con otros nombres de forma.

require_once __DIR__.'/ppt_embudo.php';

function ep_ppt_epson_day_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/epson-day.pptx',
		'titulo' => 'EPSON DAY',
		'prefijo_actividad' => 'ACTIVIDAD',
		'promotor' => ['nombre' => 'CuadroTexto 12', 'correo' => 'CuadroTexto 14', 'punto' => 'CuadroTexto 24', 'actividad' => 'CuadroTexto 26', 'fecha' => 'CuadroTexto 27', 'ciudad' => 'CuadroTexto 28', 'foto' => 'Gráfico 18'],
		'stats' => ['n' => 3, 'llenar' => 'ep_ppt_epson_day_estadisticas'],
		'fotos' => [
			'n' => 4,
			'titulo' => 'CuadroTexto 2',
			'rects' => ['Rectángulo 4', 'Rectángulo 33', 'Rectángulo 34'],
			'orden' => ['materiales', 'redes-1', 'redes-2'],
		],
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_epson_day(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_epson_day_spec(), $registros, $tituloMes, $opciones);
}

function ep_ppt_epson_day_estadisticas(DOMDocument $dom, DOMXPath $xp, array $reg): void {
	ep_ppt_estadisticas_embudo($dom, $xp, $reg, [
		'cobertura' => ['CuadroTexto 34', 'CuadroTexto 35'],
		'interaccion' => ['CuadroTexto 36', 'CuadroTexto 37'],
		'ventas' => ['CuadroTexto 41', 'CuadroTexto 42'],
		'visitaron' => 'CLIENTES QUE VISITARON LA TIENDA',
		'filas' => [
			['CuadroTexto 47', 'CuadroTexto 67', 'Rectángulo 50', 'Rectángulo 49'],
			['CuadroTexto 51', 'CuadroTexto 71', 'Rectángulo 57', 'Rectángulo 56'],
			['CuadroTexto 52', 'CuadroTexto 70', 'Rectángulo 60', 'Rectángulo 59'],
			['CuadroTexto 53', 'CuadroTexto 69', 'Rectángulo 63', 'Rectángulo 62'],
			['CuadroTexto 54', 'CuadroTexto 68', 'Rectángulo 66', 'Rectángulo 65'],
		],
		'mayor' => ['CuadroTexto 72', 'CuadroTexto 76'],
		'menor' => ['CuadroTexto 74', 'CuadroTexto 77'],
		'embudo' => [['Rectángulo 79', 'CuadroTexto 86'], ['Rectángulo 81', 'CuadroTexto 87'], ['Rectángulo 85', 'CuadroTexto 88']],
		'comentarios' => ['CuadroTexto 92', 'CuadroTexto 93', 'CuadroTexto 96', 'CuadroTexto 97', 'CuadroTexto 103'],
	]);
}
