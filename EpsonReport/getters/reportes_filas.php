<?php
// HTML actualizado de las tarjetas de Reportes mensuales, para el refresco en vivo.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';

if (!ep_login_check() || !ep_es_gestor()) {
	http_response_code(401);
	exit;
}

require_once __DIR__.'/../includes/reportes_datos.php';

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$reportes = ep_reportes_listar();
$nombresTipo = ['activaciones' => 'Activaciones', 'capacitaciones' => 'Capacitaciones', 'colocacion-pop' => 'Colocación de POP', 'epson-day' => 'Epson Day', 'exhibiciones' => 'Exhibiciones', 'evento-ferias' => 'Evento o Ferias', 'informe-fotografico' => 'Informe Fotográfico', 'competencia' => 'Competencia'];
$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__.'/../components/reportes/filas.php';
