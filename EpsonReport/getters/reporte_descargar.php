<?php
// Arma el PPTX de un reporte mensual guardado y lo envía (?id=). Solo admin. El archivo no se guarda: se genera en cada descarga.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

function ep_rep_error($mensaje, $codigo = 400, $extra = []) {
	http_response_code($codigo);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(array_merge(['success' => false, 'error' => $mensaje], $extra), JSON_UNESCAPED_UNICODE);
	exit;
}

if (!ep_login_check()) {
	ep_rep_error('Tu sesión se cerró. Inicia sesión de nuevo.', 401, ['redirect' => 'login.php?error=sesion']);
}
if (ep_rol_actual() !== 'admin') {
	ep_rep_error('No tienes permiso para descargar reportes.', 403);
}
if (!class_exists('ZipArchive')) {
	ep_rep_error('El servidor no tiene habilitada la extensión para generar PowerPoint.', 500);
}

require_once __DIR__.'/../includes/registros_datos.php';
require_once __DIR__.'/../includes/reportes_datos.php';
require_once __DIR__.'/../includes/ppt_motor.php';

$reporte = ep_reporte_obtener((int) ($_GET['id'] ?? 0));
if (!$reporte) {
	ep_rep_error('No se encontró el reporte.', 404);
}
$generador = ep_ppt_generador($reporte['tipo']);
if (!$generador) {
	ep_rep_error('Este formato de presentación todavía no está disponible.');
}

// Copia congelada al guardar; los reportes anteriores a esa copia se arman con los registros actuales.
$registros = $reporte['snapshot']['registros'] ?? ep_registros_datos(5000, $reporte['ids']);
if (empty($registros)) {
	ep_rep_error('Los registros de este reporte ya no existen.', 404);
}
// Agrupados por persona (cada una será una sección del PPTX) y por fecha dentro de ella.
usort($registros, fn($a, $b) => strcmp(($a['promotor'] ?? '').($a['fecha_actividad'] ?? $a['fecha_iso'] ?? '').($a['hora_inicio'] ?? $a['hora'] ?? ''), ($b['promotor'] ?? '').($b['fecha_actividad'] ?? $b['fecha_iso'] ?? '').($b['hora_inicio'] ?? $b['hora'] ?? '')));

@set_time_limit(300);
@ini_set('memory_limit', '768M');
$meses = ep_ppt_meses();
$titulo = $meses[(int) substr($reporte['mes'], 5, 2)].' '.substr($reporte['mes'], 0, 4);
$opciones = ['programadas' => $reporte['programadas'] !== null ? (int) $reporte['programadas'] : null, 'comentarios' => (string) ($reporte['comentarios'] ?? ''), 'nombre_actividad' => (string) ($reporte['snapshot']['actividad'] ?? '')];
if (!empty($reporte['calendario'])) {
	$opciones['calendario_url'] = EP_FOTOS_URL_BASE.'AppEpson/EpsonReport/'.$reporte['calendario'];
}
try {
	$archivo = $generador($registros, $titulo, $opciones);
} catch (Throwable $e) {
	error_log('reporte_descargar: '.$e->getMessage());
	ep_rep_error('No se pudo generar la presentación.', 500);
}
$nombre = trim(preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', (string) ($reporte['titulo'] ?: $reporte['tipo'])) ?: 'REPORTE'), '_').'.pptx';
header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="'.$nombre.'"');
header('Content-Length: '.filesize($archivo));
readfile($archivo);
unlink($archivo);
