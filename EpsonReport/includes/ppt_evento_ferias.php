<?php
// PPTX de Evento o Ferias (plantilla recursos/ppt/evento-ferias.pptx): como Activaciones, sin la tarjeta de cobertura.

require_once __DIR__.'/ppt_embudo.php';

function ep_ppt_evento_ferias_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/evento-ferias.pptx',
		'titulo' => 'EVENTOS O FERIAS',
		'prefijo_actividad' => 'EVENTO',
		'stats' => ['n' => 3, 'llenar' => 'ep_ppt_evento_ferias_estadisticas'],
		'fotos' => [
			'n' => 4,
			'titulo' => 'CuadroTexto 2',
			'rects' => ['Rectángulo 3', 'Rectángulo 4', 'Rectángulo 5'],
			'orden' => ['stand', 'interaccion-1', 'interaccion-2'],
		],
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_evento_ferias(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_evento_ferias_spec(), $registros, $tituloMes, $opciones);
}

function ep_ppt_evento_ferias_estadisticas(DOMDocument $dom, DOMXPath $xp, array $reg): void {
	ep_ppt_estadisticas_embudo($dom, $xp, $reg, [
		'interaccion' => ['CuadroTexto 96', 'CuadroTexto 97'],
		'ventas' => ['CuadroTexto 101', 'CuadroTexto 102'],
		'visitaron' => 'CLIENTES FUERON AL EVENTO',
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
