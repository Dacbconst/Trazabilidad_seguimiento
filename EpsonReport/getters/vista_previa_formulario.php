<?php
// Devuelve el HTML real de una plantilla, sin ids (evita chocar con el formulario real) y sin poder tipear — solo para la vista previa del constructor.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	exit;
}

$plantillasValidas = ['activaciones', 'capacitaciones', 'epson-day', 'evento-ferias', 'exhibiciones', 'colocacion-pop', 'generico', 'pendiente'];
$plantilla = $_GET['plantilla'] ?? '';
if (!in_array($plantilla, $plantillasValidas, true)) {
	http_response_code(400);
	exit;
}

ob_start();
include __DIR__.'/../components/actividades/plantillas/'.$plantilla.'.php';
$html = ob_get_clean();

$html = preg_replace('/\sid="[^"]*"/', '', $html);
$html = preg_replace('/\sfor="[^"]*"/', '', $html);
$html = preg_replace('/<(input|textarea|select|button)\b/', '<$1 disabled', $html);

header('Content-Type: text/html; charset=utf-8');
echo $html;
