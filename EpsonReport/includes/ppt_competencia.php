<?php
// PPTX de Competencia: formato oficial de Epson, sin estadísticas; fotos de 3 en 3 con su descripción de pie y un encabezado "REPORTE COMPETENCIA MES - CANAL".

require_once __DIR__.'/ppt_motor.php';

function ep_ppt_competencia_spec(): array {
	return [
		'plantilla' => __DIR__.'/../recursos/ppt/competencia.pptx',
		'titulo' => 'INFORME DE COMPETENCIA',
		'titulo_fijo' => true,
		'portada_titulo' => 'Título 1',
		'portada_doble' => true,
		'prefijo_actividad' => 'COMPETENCIA',
		// Sin 'orden': entran todas las fotos que traiga cada registro, también las sumadas con "+ Agregar foto".
		'fotos' => [
			'n' => 3,
			'titulo' => 'Título 1',
			'titulo_texto' => 'ep_ppt_competencia_encabezado',
			'rects' => ['Foto 1', 'Foto 2', 'Foto 3'],
			'pies' => ['Pie 1', 'Pie 2', 'Pie 3'],
			'pie_texto' => 'ep_ppt_competencia_pie',
		],
	];
}

// "REPORTE COMPETENCIA AGOSTO - RETAIL": mes de la fecha del registro y su canal.
function ep_ppt_competencia_encabezado(array $reg): string {
	$fecha = (string) ($reg['fecha_iso'] ?? '');
	$mes = $fecha !== '' ? ep_ppt_meses()[(int) substr($fecha, 5, 2)] : '';
	$canal = ep_ppt_mayus(trim((string) ($reg['canal'] ?? '')));
	return trim('REPORTE COMPETENCIA '.$mes.($canal !== '' ? ' - '.$canal : ''));
}

// Pie de la foto: lo que escribió el promotor y, debajo, dónde.
function ep_ppt_competencia_pie(array $reg, array $foto): array {
	$donde = trim(ep_ppt_mayus((string) ($reg['punto_venta'] ?? '')).' - '.ep_ppt_mayus((string) ($reg['ciudad'] ?? '')), ' -');
	return [ep_ppt_mayus((string) ($foto['descripcion'] ?? '')), $donde];
}

// Devuelve la ruta de un .pptx temporal con los registros dados. El llamador lo envía y lo borra.
function ep_ppt_competencia(array $registros, string $tituloMes, array $opciones = []): string {
	return ep_ppt_generar(ep_ppt_competencia_spec(), $registros, $tituloMes, $opciones);
}
