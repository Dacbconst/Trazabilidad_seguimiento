<?php
// Descarga varios registros seleccionados en un solo PPTX (deben ser del mismo tipo de actividad); se arma al vuelo y no se guarda. Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

function ep_multi_ppt_error($mensaje, $codigo = 400) {
	http_response_code($codigo);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['success' => false, 'error' => $mensaje], JSON_UNESCAPED_UNICODE);
	exit;
}

if (!ep_login_check()) {
	ep_multi_ppt_error('Tu sesión se cerró. Inicia sesión de nuevo.', 401);
}
if (!ep_es_gestor()) {
	ep_multi_ppt_error('No tienes permiso para descargar presentaciones.', 403);
}
if (!class_exists('ZipArchive')) {
	ep_multi_ppt_error('El servidor no tiene habilitada la extensión para generar PowerPoint.', 500);
}

require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/ppt_motor.php';

$codigos = array_values(array_unique(array_filter(array_map('trim', (array) ($_POST['codigos'] ?? [])))));
if (empty($codigos)) {
	ep_multi_ppt_error('Selecciona al menos un registro.');
}
if (count($codigos) > 100) {
	ep_multi_ppt_error('Selecciona como máximo 100 registros por descarga.');
}

$porCodigo = array_column(ep_registros_datos(5000, [], ['Aprobado']), null, 'id');
$registros = array_values(array_intersect_key($porCodigo, array_flip($codigos)));
if (empty($registros)) {
	ep_multi_ppt_error('No se encontraron los registros seleccionados.', 404);
}

$tipos = array_unique(array_column($registros, 'tipo'));
if (count($tipos) > 1) {
	ep_multi_ppt_error('Selecciona registros de un solo tipo de actividad por descarga (encontré '.count($tipos).' tipos distintos).');
}
$tipo = (string) $tipos[0];
$generador = ep_ppt_generador($tipo);
if (!$generador) {
	ep_multi_ppt_error('Este formato de presentación todavía no está disponible.');
}

@set_time_limit(180);
@ini_set('memory_limit', '512M');

try {
	$archivo = $generador($registros, '', ['solo_registro' => true]);
} catch (Throwable $e) {
	error_log('registros_ppt_multiple: '.$e->getMessage());
	ep_multi_ppt_error('No se pudo generar la presentación.', 500);
}

$nombre = 'CONSOLIDADO_'.strtoupper($tipo).'_'.count($registros).'REG_'.date('dmY_His').'.pptx';
header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="'.$nombre.'"');
header('Content-Length: '.filesize($archivo));
readfile($archivo);
unlink($archivo);
