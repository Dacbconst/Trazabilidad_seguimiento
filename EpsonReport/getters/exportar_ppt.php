<?php
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

function ep_ppt_error($mensaje, $codigo = 400) {
	http_response_code($codigo);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['success' => false, 'error' => $mensaje]);
	exit;
}

if (!ep_login_check()) {
	ep_ppt_error('Tu sesión se cerró. Inicia sesión de nuevo.', 401);
}
if (!class_exists('ZipArchive')) {
	ep_ppt_error('El servidor no tiene habilitada la extensión para generar PowerPoint.', 500);
}

require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/ppt_activaciones.php';

$tipo = $_GET['tipo'] ?? 'activaciones';
if ($tipo !== 'activaciones') {
	ep_ppt_error('Este formato de presentación todavía no está disponible.');
}

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
	ep_ppt_error('Mes inválido.');
}
// Descargar presentaciones es solo del administrador.
if (ep_rol_actual() !== 'admin') {
	ep_ppt_error('No tienes permiso para descargar presentaciones.', 403);
}
$usuario = trim($_GET['usuario'] ?? 'all');

$registros = array_values(array_filter(ep_registros_datos(5000), function ($r) use ($tipo, $mes, $usuario) {
	return ($r['tipo'] ?? '') === $tipo
		&& strpos($r['fecha_iso'] ?? '', $mes) === 0
		&& ($usuario === 'all' || $usuario === '' || strcasecmp($r['promotor_usuario'] ?? '', $usuario) === 0 || strcasecmp($r['promotor'] ?? '', $usuario) === 0);
}));
if (empty($registros)) {
	ep_ppt_error('No hay registros de Activaciones para ese mes y usuario.', 404);
}
// Cronológico: del primero al último del mes.
usort($registros, fn($a, $b) => strcmp(($a['fecha_iso'] ?? '').($a['hora'] ?? ''), ($b['fecha_iso'] ?? '').($b['hora'] ?? '')));

@set_time_limit(120);
@ini_set('memory_limit', '512M');
$meses = ep_ppt_meses();
$titulo = $meses[(int) substr($mes, 5, 2)].' '.substr($mes, 0, 4);

try {
	$archivo = ep_ppt_activaciones($registros, $titulo);
} catch (Throwable $e) {
	error_log('exportar_ppt: '.$e->getMessage());
	ep_ppt_error('No se pudo generar la presentación.', 500);
}

$nombre = 'ACTIVACIONES_'.str_replace(' ', '_', $titulo).'.pptx';
header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="'.$nombre.'"');
header('Content-Length: '.filesize($archivo));
readfile($archivo);
unlink($archivo);
