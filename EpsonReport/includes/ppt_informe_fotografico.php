<?php
// PPTX de Informe Fotográfico Simple (Exhibiciones Regulares, Competencia): sin estadísticas, solo fotos con el punto de venta como título.

require_once __DIR__.'/ppt_motor.php';

function ep_ppt_informe_fotografico_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/informe-fotografico.pptx',
		'titulo' => 'INFORME FOTOGRÁFICO',
		'prefijo_actividad' => 'INFORME',
		// Sin 'orden' a propósito: esta actividad no tiene tope de fotos, se usan todas las que traiga cada registro.
		'fotos' => [
			'n' => 4,
			'titulo' => 'CuadroTexto 1',
			'rects' => ['Rectángulo 3', 'Rectángulo 4', 'Rectángulo 5'],
		],
	];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_informe_fotografico(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_informe_fotografico_spec(), $registros, $tituloMes, $opciones);
}
