<?php
// HTML actualizado de las filas del Historial para el refresco en vivo; el promotor solo recibe las suyas.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check()) {
	http_response_code(401);
	exit;
}
require_once __DIR__.'/../includes/fotos_datos.php';
require_once __DIR__.'/../includes/registros_datos.php';

$esAdmin = (ep_rol_actual() === 'admin');
$todosRegistros = ep_registros_datos();
if (!$esAdmin) {
	$usuarioSesion = $_SESSION['usuario'] ?? '';
	$todosRegistros = array_values(array_filter($todosRegistros, fn($r) => strcasecmp($r['promotor_usuario'] ?? '', $usuarioSesion) === 0));
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
include __DIR__.'/../components/historial/filas.php';
