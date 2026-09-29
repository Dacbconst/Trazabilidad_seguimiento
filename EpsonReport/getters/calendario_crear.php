<?php
// Crea un Calendario de Activaciones (solo admin) con sus filas y lo activa de inmediato. No existe borrador.
require_once __DIR__.'/../config.php';
session_set_cookie_params(0, '/', '', SECURE, true);
session_start();
header('Content-Type: application/json; charset=utf-8');

if (($_SESSION['rol'] ?? '') !== 'admin') {
	http_response_code(403);
	echo json_encode(['ok' => false, 'message' => 'No autorizado.']);
	exit;
}

require_once __DIR__.'/../includes/calendario_datos.php';

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$canal = ($_POST['canal'] ?? '') === 'CANALES' ? 'CANALES' : 'RETAIL';
$plazoDias = max(1, min(30, (int) ($_POST['plazo_dias'] ?? 5)));
$filasJson = json_decode($_POST['filas'] ?? '[]', true);

if (!is_array($filasJson) || empty($filasJson)) {
	echo json_encode(['ok' => false, 'message' => 'Agrega al menos una fila.']);
	exit;
}

$filas = [];
foreach ($filasJson as $f) {
	$fecha = $f['fecha'] ?? '';
	$posId = $f['pos_id'] ?? '';
	$promotorId = (int) ($f['promotor_id'] ?? 0);
	// El servidor nunca confía en lo que manda el navegador: cada combinación se revalida contra el rutero real.
	$real = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? ep_calendario_rutero_validar($canal, $promotorId, $posId) : null;
	if (!$real) {
		echo json_encode(['ok' => false, 'message' => 'Una de las filas tiene un punto de venta, promotor o fecha inválidos.']);
		exit;
	}
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

$id = ep_calendario_crear($nombre, $canal, $plazoDias, $filas, (int) $_SESSION['usuario_id']);
echo json_encode($id ? ['ok' => true, 'id' => $id] : ['ok' => false, 'message' => 'No se pudo crear el calendario.']);
