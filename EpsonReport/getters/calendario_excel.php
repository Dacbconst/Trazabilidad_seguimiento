<?php
// Descarga en .xlsx las filas de un calendario: fecha, ciudad, punto de venta, promotor, supervisor y si se ejecutó.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(403);
	echo 'No tienes permiso para esto.';
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';
require_once __DIR__.'/../includes/xlsx_motor.php';

$id = (int) ($_GET['id'] ?? 0);
if (!ep_calendario_permitido($id)) {
	http_response_code(403);
	echo 'Ese calendario no es tuyo.';
	exit;
}
$cal = ep_calendario_obtener($id);
if (!$cal) {
	http_response_code(404);
	echo 'Calendario no encontrado.';
	exit;
}

$filas = ep_calendario_filas_tabla($id);
$filasXlsx = array_map(fn($f) => [$f['fecha'], $f['ciudad'], $f['punto_venta'], $f['promotor'], $f['supervisor'] ?: 'Sin asignar', $f['estado'] === 'cumplido' ? 'Sí' : 'No'], $filas);

$archivo = ep_xlsx_generar(['Fecha', 'Ciudad', 'Punto de venta', 'Promotor', 'Supervisor', 'Ejecutado'], $filasXlsx);
$nombreArchivo = preg_replace('/[^A-Za-z0-9 _-]/', '', ep_calendario_nombre($cal)).'.xlsx';
ep_xlsx_descargar($archivo, $nombreArchivo);
