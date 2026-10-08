<?php
// Crea un Calendario de Activaciones (solo admin) con sus filas y lo activa de inmediato. No existe borrador.
require_once __DIR__.'/../config.php';
session_set_cookie_params(EP_COOKIE_VIDA, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!in_array($_SESSION['rol'] ?? '', ['admin', 'supervisor'], true)) {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$canal = (string) ($_POST['canal'] ?? '');
if (!in_array($canal, ep_calendario_canales(), true)) {
	echo json_encode(['ok' => false, 'message' => 'Canal inválido.']);
	exit;
}
$plazoDias = max(1, min(30, (int) ($_POST['plazo_dias'] ?? 5)));
$filasJson = json_decode($_POST['filas'] ?? '[]', true);
$mes = (string) ($_POST['mes'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
	echo json_encode(['ok' => false, 'message' => 'Elige el mes del calendario.']);
	exit;
}

if (!is_array($filasJson) || empty($filasJson)) {
	echo json_encode(['ok' => false, 'message' => 'Agrega al menos una fila.']);
	exit;
}

$filas = [];
$vistas = [];
foreach ($filasJson as $f) {
	$fecha = $f['fecha'] ?? '';
	$posId = $f['pos_id'] ?? '';
	$promotorId = (int) ($f['promotor_id'] ?? 0);
	// No se confía en el navegador: cada fila se revalida contra la base.
	$real = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? ep_calendario_fila_validar($canal, $promotorId, $posId) : null;
	if (!$real || substr($fecha, 0, 7) !== $mes) {
		echo json_encode(['ok' => false, 'message' => 'Una de las filas tiene un punto de venta, promotor o fecha inválidos (todas deben ser del mes elegido).']);
		exit;
	}
	// Un promotor no puede tener el mismo punto el mismo día en otro calendario activo ni dos veces en este.
	$llave = $promotorId.'|'.$posId.'|'.$fecha;
	$previo = ep_calendario_fila_repetida($promotorId, $posId, $fecha);
	if ($previo !== null || isset($vistas[$llave])) {
		echo json_encode(['ok' => false, 'message' => ep_calendario_fila_repetida_mensaje($real['promotor_nombre'], $fecha, $previo ?? 'este calendario')]);
		exit;
	}
	$vistas[$llave] = true;
	$filas[] = [
		'fecha' => $fecha,
		'pos_id' => $posId,
		'punto_venta' => $real['punto_venta'],
		'ciudad' => $real['ciudad'],
		'promotor_usuario_id' => $promotorId,
		'promotor_nombre' => $real['promotor_nombre'],
		'supervisor_nombre' => $real['supervisor'],
	];
}

// En este servidor (nginx + PHP 8.2) un error fatal sale como "404"; se atrapa para devolver el motivo real.
try {
	$comentarios = ep_calendario_limpiar_comentarios((string) ($_POST['comentarios'] ?? ''));
	$id = ep_calendario_crear($nombre, $canal, $plazoDias, $comentarios, $filas, (int) $_SESSION['usuario_id']);
	if ($id) {
		require_once __DIR__.'/../includes/auditoria_datos.php';
		$fechas = array_column($filas, 'fecha');
		sort($fechas);
		ep_auditar('calendario_crear', 'calendario', $id, 'Creó el calendario «'.ep_calendario_nombre(['nombre' => $nombre, 'canal' => $canal]).'»', [
			ep_auditoria_dato('Canal', $canal),
			ep_auditoria_dato('Filas', count($filas)),
			ep_auditoria_dato('Fechas', date('d/m/Y', strtotime($fechas[0])).' al '.date('d/m/Y', strtotime(end($fechas)))),
			ep_auditoria_dato('Plazo', $plazoDias.' días'),
		]);
	}
} catch (Throwable $e) {
	error_log('calendario_crear: '.$e->getMessage());
	echo json_encode(['ok' => false, 'message' => 'Error del servidor: '.$e->getMessage()]);
	exit;
}
echo json_encode($id ? ['ok' => true, 'id' => $id] : ['ok' => false, 'message' => 'No se pudo crear el calendario.']);
