<?php
// Rutero, ciudades y PDV por canal para el modal del Calendario (solo admin); se piden aparte para no cargar cientos de KB en el HTML.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
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
	$datos['rutero'][$canal] = ep_calendario_rutero($canal);
	$datos['ciudades'][$canal] = ep_calendario_ciudades($canal);
	$datos['pdv'][$canal] = ep_calendario_pdv($canal);
	if ($esSupervisor) {
		$equipo = ep_promotores_de_supervisor($miId, $canal);
		$datos['rutero'][$canal] = array_values(array_filter($datos['rutero'][$canal], fn($f) => in_array(mb_strtoupper($f['promotor_nombre'], 'UTF-8'), $equipo, true)));
		$ciudadesEquipo = array_unique(array_column($datos['rutero'][$canal], 'ciudad'));
		$datos['ciudades'][$canal] = array_values(array_filter($datos['ciudades'][$canal], fn($c) => in_array(is_array($c) ? ($c['ciudad'] ?? '') : $c, $ciudadesEquipo, true)));
		// El PDV se filtraba antes rutero/ciudades pero no a sí mismo: un supervisor recibía el catálogo completo del canal, de todas las ciudades.
		$datos['pdv'][$canal] = array_values(array_filter($datos['pdv'][$canal], fn($p) => in_array($p['ciudad'] ?? '', $ciudadesEquipo, true)));
	}
}

// nginx no comprime JSON en este servidor: se comprime aquí (de ~650 KB a ~60 KB).
if (!ob_start('ob_gzhandler')) {
	ob_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
