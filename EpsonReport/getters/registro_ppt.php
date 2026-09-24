<?php
// PPTX de un solo registro (estadísticas y fotos con el punto de venta) del tipo de actividad que tenga generador; se arma al descargar y no se guarda. Solo admin.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

function ep_reg_ppt_error($mensaje, $codigo = 400, $extra = []) {
	http_response_code($codigo);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array_merge(['success' => false, 'error' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
	exit;
}

if (!ep_login_check()) {
	ep_reg_ppt_error('Tu sesión se cerró. Inicia sesión de nuevo.', 401, ['redirect' => 'login.php?error=sesion']);
}
if (ep_rol_actual() !== 'admin') {
	ep_reg_ppt_error('No tienes permiso para descargar presentaciones.', 403);
}
if (!class_exists('ZipArchive')) {
	ep_reg_ppt_error('El servidor no tiene habilitada la extensión para generar PowerPoint.', 500);
}

require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/ppt_motor.php';

$registro = ep_registro_por_codigo(trim($_GET['id'] ?? ''));
if (!$registro) {
	ep_reg_ppt_error('No se encontró el registro.', 404);
}
$generador = ep_ppt_generador((string) ($registro['tipo'] ?? ''));
if (!$generador) {
	ep_reg_ppt_error('Este formato de presentación todavía no está disponible.');
}

@set_time_limit(120);
@ini_set('memory_limit', '512M');

try {
	$archivo = $generador([$registro], '', ['solo_registro' => true]);
} catch (Throwable $e) {
	error_log('registro_ppt: '.$e->getMessage());
	ep_reg_ppt_error('No se pudo generar la presentación.', 500);
}

$nombre = strtoupper((string) $registro['tipo']).'_'.preg_replace('/[^A-Z0-9]+/', '_', strtoupper((string) ($registro['promotor'] ?? 'REGISTRO'))).'_'.($registro['fecha_iso'] ?? '').'.pptx';
header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="'.$nombre.'"');
header('Content-Length: '.filesize($archivo));
readfile($archivo);
unlink($archivo);
