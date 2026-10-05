<?php
// Rutero, ciudades y PDV por canal para el modal del Calendario (solo admin); se piden aparte para no cargar cientos de KB en el HTML.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
$esAdmin = in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true);
$miRol = $_SESSION['rol'] ?? '';
$miId = (int) ($_SESSION['usuario_id'] ?? 0);
session_write_close();

if (!$esAdmin) {
	http_response_code(403);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$datos = ['rutero' => [], 'ciudades' => [], 'pdv' => []];
$esSupervisor = ($miRol ?? '') === 'supervisor';
if ($esSupervisor) {
	require_once __DIR__.'/../includes/aprobacion_datos.php';
}
foreach (ep_calendario_canales() as $canal) {
	$deLaApp = ep_calendario_supervisores_app($canal);
	// El supervisor mostrado es el que aprueba en la app, no el del rutero de Xplora.
	$datos['rutero'][$canal] = array_map(fn($f) => ['supervisor' => $deLaApp[mb_strtoupper($f['promotor_nombre'], 'UTF-8')] ?? $f['supervisor']] + $f, ep_calendario_rutero($canal));
	$datos['ciudades'][$canal] = ep_calendario_ciudades($canal);
	$datos['pdv'][$canal] = ep_calendario_pdv($canal);
	if ($esSupervisor) {
		$equipo = ep_promotores_de_supervisor($miId, $canal);
		$datos['rutero'][$canal] = array_values(array_filter($datos['rutero'][$canal], fn($f) => in_array(mb_strtoupper($f['promotor_nombre'], 'UTF-8'), $equipo, true)));
		// Ciudades y puntos del canal completos: el rutero cambia seguido y no debe limitar al promotor.
	}
}

// nginx no comprime JSON en este servidor: se comprime aquí (de ~650 KB a ~60 KB).
if (!ob_start('ob_gzhandler')) {
	ob_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
