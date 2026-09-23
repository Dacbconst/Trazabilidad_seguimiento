<?php
// Registros disponibles para armar un reporte mensual: ?tipo=activaciones&mes=2026-09 (solo admin).
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
require_once __DIR__.'/../includes/functions.php';
require_once __DIR__.'/../includes/registros_datos.php';
header('Content-Type: application/json; charset=utf-8');

if (!ep_login_check()) {
	http_response_code(401);
	echo json_encode(['success' => false, 'error' => 'Tu sesión se cerró. Inicia sesión de nuevo.', 'redirect' => 'login.php?error=sesion']);
	exit;
}
if (ep_rol_actual() !== 'admin') {
	http_response_code(403);
	echo json_encode(['success' => false, 'error' => 'No tienes permiso para esto.']);
	exit;
}

$tipo = preg_replace('/[^a-z-]/', '', (string) ($_GET['tipo'] ?? ''));
$mes = (string) ($_GET['mes'] ?? '');
if ($tipo === '' || !preg_match('/^\d{4}-\d{2}$/', $mes)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'error' => 'Elige el tipo de actividad y el mes.']);
	exit;
}

$lista = [];
foreach (ep_registros_datos(5000) as $r) {
	if (($r['tipo'] ?? '') !== $tipo || strpos($r['fecha_iso'] ?? '', $mes) !== 0) {
		continue;
	}
	$lista[] = [
		'id'       => $r['db_id'],
		'codigo'   => $r['id'],
		'promotor' => $r['promotor'] ?? '',
		'fecha'    => $r['fecha_iso'] ?? '',
		'hora'     => $r['hora'] ?? '',
		'fotos'    => count(array_filter($r['fotos'] ?? [], fn($f) => !empty($f['ruta']))),
	];
}
usort($lista, fn($a, $b) => strcmp($a['fecha'].$a['hora'], $b['fecha'].$b['hora']));
echo json_encode(['success' => true, 'registros' => $lista], JSON_UNESCAPED_UNICODE);
