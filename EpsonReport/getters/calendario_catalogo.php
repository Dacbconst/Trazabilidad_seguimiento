<?php
// Rutero, ciudades y PDV por canal para el modal del Calendario (solo admin); se piden aparte para no cargar cientos de KB en el HTML.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
$esAdmin = ($_SESSION['rol'] ?? '') === 'admin';
session_write_close();

if (!$esAdmin) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$datos = ['rutero' => [], 'ciudades' => [], 'pdv' => []];
foreach (ep_calendario_canales() as $canal) {
	$datos['rutero'][$canal] = ep_calendario_rutero($canal);
	$datos['ciudades'][$canal] = ep_calendario_ciudades($canal);
	$datos['pdv'][$canal] = ep_calendario_pdv($canal);
}

// nginx no comprime JSON en este servidor: se comprime aquí (de ~650 KB a ~60 KB).
if (!ob_start('ob_gzhandler')) {
	ob_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
