<?php
// Edita el punto de venta o el promotor de una fila (solo admin), nunca la fecha; se revalida contra el rutero real.
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

$filaId = (int) ($_POST['fila_id'] ?? 0);
$canal = ($_POST['canal'] ?? '') === 'CANALES' ? 'CANALES' : 'RETAIL';
$posId = $_POST['pos_id'] ?? '';
$promotorId = (int) ($_POST['promotor_id'] ?? 0);

$real = $filaId > 0 ? ep_calendario_rutero_validar($canal, $promotorId, $posId) : null;
if (!$real) {
	echo json_encode(['ok' => false, 'message' => 'Punto de venta o promotor no válidos.']);
	exit;
}

$ok = ep_calendario_fila_editar($filaId, $posId, $real['punto_venta'], $real['ciudad'], $promotorId, $real['promotor_nombre'], $real['supervisor'], (int) $_SESSION['usuario_id']);
echo json_encode($ok ? ['ok' => true, 'punto_venta' => $real['punto_venta'], 'ciudad' => $real['ciudad'], 'promotor_nombre' => $real['promotor_nombre'], 'supervisor' => $real['supervisor']] : ['ok' => false, 'message' => 'No se pudo editar (¿ya pasó la fecha?).']);
